# Upgrade

## 1.0.0 → 1.1.0

1. **With two-factor authentication on for customers, open the storefront OAuth routes.** While a
   sign-in waits for its code, scheb sends a request to a page without a `PUBLIC_ACCESS` rule back
   to the code page. The plugin now ends the pending sign-in before that for a page load (item 2),
   but not for other requests, nor on a shop firewall with its own
   `two_factor.authentication_required_handler`. Add the three rules above your remaining ones in
   `config/packages/security.yaml`:

   ```yaml
   security:
       access_control:
           - { path: ^/oauth/[a-z_]+/start, role: PUBLIC_ACCESS }
           - { path: ^/oauth/[a-z_]+/callback, role: PUBLIC_ACCESS }
           - { path: ^/oauth/confirm-link, role: PUBLIC_ACCESS }
   ```

   A sign-in that waits for its code still cannot link a provider to the account through them. See
   [Firewall](docs/oauth-social-login.md#firewall).

2. **Leaving the code page now ends the pending sign-in** on the shop and admin firewalls when
   they have a `two_factor` block, with nothing to configure. What it changes is described in
   [Leaving the code page](docs/two-factor-authentication.md#leaving-the-code-page):
   - `PUBLIC_ACCESS` responses to visitors with a session cookie are sent with
     `Cache-Control: private`. Check this if an HTTP cache serves those pages.
   - The services are `three_brs.two_factor.pending_sign_in_canceller.<firewall>` and
     `three_brs.two_factor.cancel_pending_sign_in_required_handler.<firewall>`. The plugin registers
     each of them only when the application has not defined a service under that id, so a definition
     of your own takes its place; the handler then gets your canceller.
   - If you built the same thing in your application, it can go.

3. **A firewall without two-factor authentication needs no `two_factor` block.** If you added the
   block, and with it the shop `success_handler`, only so that the container compiled, remove both:
   without the block the plugin does not register that success handler. On a firewall with 2FA
   nothing changes: the shop `success_handler` keeps its service id,
   `ThreeBRS\EnterpriseSecurityBundle\TwoFactor\TwoFactorAwareAuthenticationSuccessHandler.shop`.

4. **Update the templates you override.**
   - `Admin/TwoFactor/{setup,manage,recovery_codes}.html.twig` — include
     `@SyliusAdmin/shared/crud/common/content/flashes.html.twig` below the page header, as the
     plugin's templates now do.
   - `Admin/Customer/Security/section.html.twig` — mark a login-history row online only when the
     session is among the active sessions, as the plugin's template does with `activeSessionIds`;
     checking `session.revoked` alone shows an expired session online.
   - `Shop/Sessions/index.html.twig`, `Admin/Sessions/index.html.twig` — show the "sign out other
     sessions" button and its modal on
     `three_brs_has_other_unrevoked_sessions(app.user, app.session.id ?? '')` instead of
     `rows|length > 1`. The list leaves out expired sessions, and one that is still signed in could
     not be signed out while the list shows only the current session.
   - `TwoFactor/challenge.html.twig` — an override still replaces the code page of both firewalls. To
     restyle only the storefront one, move it to `Shop/TwoFactor/challenge.html.twig` and drop the
     admin branch.

5. **Update your subclasses and service definitions.** If you extend one of these classes or define
   its service yourself, add the new constructor arguments, which come after the existing ones, and
   pass them on to `parent::__construct()`; the plugin's `config/services.yaml` shows the services
   they take:
   - `Controller\Shop\{PasskeyLoginVerify,OAuthCallback,OAuthConfirmLink,MagicLinkVerify}Controller`
     — `$eventDispatcher`.
   - `Repository\{CustomerSession,AdminUserSession}Repository` — `$sessionLifetime`.

   An own implementation of `CustomerSessionRepositoryInterface` or
   `AdminUserSessionRepositoryInterface` needs `findUnrevokedForShopUser()` or
   `findUnrevokedForAdminUser()`, returning every session of the user that has not been revoked.
   `findActiveForShopUser()` and `findActiveForAdminUser()` now leave expired sessions out; code
   that used them for every session not revoked yet should call the new methods.

   An own implementation of `CustomerSessionTrackerInterface` or `AdminUserSessionTrackerInterface`
   needs `moveSession()`, which moves a recorded session to a new session ID. The session login
   handlers call it when a sign-in gives the session a new ID.

   A service of yours that takes scheb's concrete `TotpAuthenticator` class instead of
   `TotpAuthenticatorInterface` now receives the plugin's `NormalizingTotpAuthenticator`, which
   implements only the interface; type the argument with the interface.

6. **Check the session lifetime.** The active session lists now hide a session whose last recorded
   activity is older than the session lifetime plus 60 seconds. The lifetime defaults to
   `session.gc_maxlifetime`; if your session handler keeps sessions for another time — a TTL set on
   a Redis handler, for instance — set it in seconds:

   ```yaml
   three_brs_sylius_enterprise_security:
       session_management:
           lifetime: 86400
   ```

7. **Update one translation** if you serve other locales than English:
   `three_brs.ui.social_login.auto_register_refused` in the `flashes` domain changed its text.

8. **Install the bundled assets again** with `bin/console assets:install`. `totp_input.js`
   changed, and a copy installed earlier shows no mask and still removes the dash from the code when
   the form is sent.
