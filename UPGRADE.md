# Upgrade

## 1.1.0 → 1.2.0

1. **`3brs/enterprise-security-bundle` 2.5.0 comes with the plugin.** Nothing in your wiring changes.
   On PHP 8.4 Composer also moves `scheb/2fa-*` to 8, with Symfony 7.4 too — see the bundle's
   [UPGRADE.md](https://github.com/3BRS/enterprise-security-bundle/blob/main/UPGRADE.md#240--250)
   for the two scheb changes that can reach your own code.

2. **If you extend `ShopUserLockoutChecker` or `AdminUserLockoutChecker` and override
   `checkPostAuth()`**, add the optional token parameter the parent method now declares:

   ```php
   public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
   ```

3. **On Symfony 8 with api-platform 4.3.x, set `framework.property_info.with_constructor_extractor` to
   `false`.** Symfony 8 turns the option on by default, and on api-platform 4.3.x the extractor then
   resolves relation properties to `object` instead of the related class that nested IRIs are built
   from — see [Troubleshooting](README.md#unable-to-generate-an-iri-for-the-item-of-type--on-api-responses).

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
   - `Shop/TwoFactor/manage.html.twig`, `Admin/TwoFactor/manage.html.twig` — add a `_code` field to
     both the disable and the regenerate form. Without it every attempt is refused with
     `three_brs.two_factor.confirmation_code_invalid`:

     ```twig
     <input type="text" name="_code" class="form-control" required autocomplete="one-time-code"
            placeholder="{{ 'three_brs.ui.two_factor.confirmation_code'|trans }}">
     ```

     Leave `data-totp-code` off: the field takes a recovery code as well, and `totp_input.js` cuts a
     field that carries the attribute down to six digits.
   - `Shop/Passkey/index.html.twig`, `Admin/Passkey/index.html.twig` — add
     `data-reauthenticate-message="{{ 'three_brs.ui.passkey.registration_reauthenticate'|trans }}"` to
     the register button, next to `data-error-message`. Without it a user signed in through a
     remember-me cookie gets the generic error when the registration answers `403`.
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
   - `Controller\{Shop,Admin}\TwoFactorDisableController` — `$recoveryGenerator`,
     `$totpAuthenticator`, `$rateLimitGuard`.
   - `Controller\{Shop,Admin}\TwoFactorRegenerateRecoveryCodesController` — `$totpAuthenticator`,
     `$rateLimitGuard`.
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

7. **Update the translations** if you serve other locales than English:
   `three_brs.ui.two_factor.confirmation_code`, `three_brs.ui.two_factor.confirmation_code_help` and
   `three_brs.ui.passkey.registration_reauthenticate` in the `messages` domain are new,
   `three_brs.two_factor.confirmation_code_invalid` in `flashes` is new as well (its English wording
   ships with the bundle), and `three_brs.ui.social_login.auto_register_refused` in `flashes` changed
   its text.

8. **Install the bundled assets again** with `bin/console assets:install`. With a copy installed
   earlier, `totp_input.js` shows no mask and still removes the dash from the code when the form is
   sent, and `passkey.js` shows the generic error instead of asking to sign in again.
