# Changelog

Notable changes to `3brs/sylius-enterprise-security-plugin`. Follows
[Keep a Changelog](https://keepachangelog.com/) and [SemVer](https://semver.org/).

## [1.1.0] - 2026-10-05

### Security
- **A sign-in that waits for its two-factor code can no longer link an OAuth account.** It could
  link a provider account and from then on sign in through it without the code. Bundle 2.4.0
  refuses it in its abstract controllers, together with adding a passkey and managing two-factor
  authentication, and refuses a sign-in restored from a remember-me cookie as well. The plugin's
  controllers let the remember-me sign-in through (`CompletedSignInGuardTrait`), so a user the
  cookie signed in does not have to sign in again. The plugin already passes `Security` to both
  OAuth initiate controllers, so the check covers the shop and the admin. The OAuth guide lists
  `PUBLIC_ACCESS` for the storefront OAuth routes as well — with customer 2FA on, scheb sends a
  request to a page without such a rule back to the code page while a sign-in waits for its code —
  and states that an OAuth sign-in skips the second factor on purpose. See
  [UPGRADE.md](UPGRADE.md#100--110).

### Added
- **Leaving the code page ends the pending sign-in.** Scheb kept a sign-in that waited for its code
  until the code was entered, the user signed out or the session expired, and sent every page
  without a `PUBLIC_ACCESS` rule back to the code page, so a customer who clicked the logo kept
  landing on it. For the shop and admin firewalls, when they have a `two_factor` block, the plugin
  now registers the bundle's `PendingSignInCanceller` and `CancelPendingSignInRequiredHandler`: once
  the code page was shown, opening another page ends the sign-in and the page opens signed out; the
  sign-in page ends it at once. XHR and prefetch requests from the code page do not. To find a
  pending sign-in, `PUBLIC_ACCESS` page loads from visitors with a session cookie read the session,
  so those responses are sent with `Cache-Control: private` — see
  [Leaving the code page](docs/two-factor-authentication.md#leaving-the-code-page).
- **`session_management.lifetime`** — how long a session lives after its last request, in seconds.
  Left at `null`, the default, the plugin reads PHP's `session.gc_maxlifetime` at run time, so a
  value set through `framework.session.gc_maxlifetime` applies as well. The new `SessionLifetime`
  service (`SessionLifetimeInterface`) turns it into the cut-off the session repositories use.
- **A code-page template per firewall.** `Admin/TwoFactor/challenge.html.twig` renders the admin
  code page and `Shop/TwoFactor/challenge.html.twig` the storefront one.
  `TwoFactor/challenge.html.twig`, the template `totp.template` names, picks between them by the
  challenge route, so an application that restyles the storefront code page overrides the shop
  template alone.

### Changed
- **`3brs/enterprise-security-bundle` is pinned to `~2.4.0`.**
- **Constructors of four controllers and two repositories gained arguments**, each added after the
  existing ones — see [UPGRADE.md](UPGRADE.md#100--110) if you extend them or define their services
  yourself:
  `Controller\Shop\{PasskeyLoginVerify,OAuthCallback,OAuthConfirmLink,MagicLinkVerify}Controller`
  (`$eventDispatcher`) and `Repository\{CustomerSession,AdminUserSession}Repository`
  (`$sessionLifetime`).
- **`findActiveForShopUser()` and `findActiveForAdminUser()` return only sessions that have not
  expired.** The new `findUnrevokedForShopUser()` and `findUnrevokedForAdminUser()` on the repository
  interfaces return every session that has not been revoked, expired ones included; the session
  trackers revoke through them.
- **`TwoFactorAwareAuthenticationSuccessHandler.shop` is registered by the new compiler pass
  `TwoFactorFirewallPass`**, under the same id, and only when the shop firewall has a `two_factor`
  block without an `authentication_required_handler` of its own. A service the application defines
  under that id is kept.
- **The code field shows the dash throughout.** `totp_input.js` wrote the code as `123-456` only
  from the fourth digit, under a grey `XXX-XXX` placeholder that disappeared with the first one, and
  removed the dash when the form was sent, so the field showed `123456` until the next page loaded.
  A grey mask now shows the positions still to fill (`1XX-XXX`, `12X-XXX`, …) and the dash stays
  when the form is sent. The new `NormalizingTotpAuthenticator` removes it on the server: it
  decorates scheb's `scheb_two_factor.security.totp_authenticator`, so a code written with a dash
  passes wherever a TOTP code is checked.

### Fixed
- **Signing in with a provider for the email of a guest customer no longer ends in a 500.** The
  registration always created a new customer, and the flush failed on the unique
  `sylius_customer.email_canonical` when a customer with that email had ordered as a guest. The
  account is now created on that customer, enabled and verified, with the first and last name taken
  from the provider where the customer has none, and the guest orders stay with it. That happens
  only when the provider reports the email as verified, which of the bundled providers Google does:
  anyone who could claim the address at a provider that does not verify it would otherwise get the
  guest's orders and addresses. An Apple or Microsoft sign-in for such an email is refused like any
  other refused auto-registration, whose message now says the account can be created another way
  instead of only pointing to an administrator.
- **Passkey, OAuth and magic-link sign-ins on the storefront take over the guest cart.** The four
  controllers that sign the customer in write the security token themselves and dispatched no
  event, so the cart stayed a guest cart (checkout asked for an email again) and the last login was
  not recorded. They now dispatch `UserEvents::SECURITY_IMPLICIT_LOGIN`, which Sylius's cart blamer,
  cart recalculation and last-login listeners act on.
- **The active session lists leave out expired sessions.** A session the session handler had
  deleted stayed listed as active on the customer's sessions page, among the active sessions on the
  admin customer detail (with a sign-out-from-all-devices button for sessions that no longer ran)
  and on the administrator's sessions page, and the login history marked it online. A session now
  counts as active while its last recorded activity is newer than the session lifetime plus 60
  seconds, the interval at which activity is recorded; the login history marks the others offline.
  No rows are deleted. Signing out other sessions still takes expired ones, which can still be
  signed in, so the sessions pages offer it while another session has not been revoked, listed or
  not (`three_brs_has_other_unrevoked_sessions()`).
- **A sign-in with two-factor authentication records one session.** The session was recorded after
  the password and again after the code, under the session ID Symfony switches to when the sign-in
  completes, so the sessions pages listed the same sign-in twice until the first entry expired. The
  session login handlers now remember the ID they recorded the session under, and when it changes the
  session moves to the new ID (the new `moveSession()` on the session trackers).
- **A rate-limited XHR request is answered with `429` JSON.** Only a JSON body was; a sign-in that
  JavaScript submitted as a form with `X-Requested-With: XMLHttpRequest` got a redirect with a flash,
  which `fetch()` followed and the page it rendered swallowed. A `429` answer no longer leaves the
  flash behind for the next page either.
- **The admin 2FA pages show flash messages.** The setup, manage and recovery-codes pages rendered
  none, so the enforcement warning never appeared on setup and turned up several times on the next
  admin page that rendered flashes. The enforcement listener also adds the warning only once until
  it is shown.
- **A firewall without two-factor authentication needs no `two_factor` block.** The shop success
  handler referred to a service scheb creates only for a firewall with the block, so without it the
  container did not compile, whether the application used 2FA or not.
- **`auto_unlock_after: ~` no longer stops the container from compiling.** The account-lockout guide
  shows `~` as the default, but the option took only integers.
- The README states that the storefront routes of the sign-in flows have no `/{_locale}` in their
  path, and how a shop with the locale in its URLs defines them again.
- The two-factor guide states that `scheb_two_factor.trusted_device.key` needs at least 32
  characters and that an unset environment variable fails every page, since scheb reads the key on
  every response.

## [1.0.0] - 2026-09-18

First stable release. Sixteen security features for Sylius, each with its own guide under
[`docs/`](docs/) and each configurable at runtime from a single admin page.

### Added

- **Password Policy** — configurable complexity (min/max length, uppercase, lowercase, number,
  special character) per customer and admin group, replacing Sylius's three-character default.
  The only feature with no on/off switch: it applies as soon as the plugin is installed.
- **Password History** — refuses reuse of recent passwords, remembered count per group, customer
  and admin history stored apart.
- **Password Expiration** — forces a change after a configurable number of days, with an optional
  `force_change` on next login. Accounts that never changed their password are measured from their
  creation date, so switching the feature on does not expire everyone at once.
- **Password Change Notifications** — email on every password change, with timestamp, IP address
  and a secure-account link. The mail states whether the user made the change themselves.
- **Two-Factor Authentication** — TOTP with QR-code setup, single-use recovery codes, trusted
  devices, and per-group `disabled` / `allowed` / `enforced` modes. Built on `scheb/2fa-bundle`.
- **3rd-party OAuth (Social Login)** — Google, Apple and Microsoft per group, with account-link
  takeover protection through a single-use emailed code, optional domain-gated auto-registration,
  and an extensible provider registry.
- **Magic Link Login** — passwordless email sign-in with single-use hashed tokens, anti-enumeration,
  timing-attack padding and rate limiting.
- **Passkey Login (WebAuthn / FIDO2)** — Touch ID, Windows Hello, Android lock and hardware keys,
  multiple labelled credentials per user, built on `web-auth/webauthn-lib`.
- **Account Lockout & Rate Limiting** — persistent per-user lockout after N failed sign-ins, with
  automatic or administrator unlock, plus ephemeral per-endpoint rate limiting for login, password
  reset and magic link in both groups, and registration for customers.
- **Session Management & Login Notifications** — active-session listing with single or all-other
  revocation, optional email alert on sign-in from a previously unseen device, pluggable GeoIP.
- **Centralized Security Settings UI** — `/admin/security-settings` configures every feature at
  runtime; values persist in the database and apply on the next request.
- **Self-Service Account Deletion (GDPR)** — customer-driven erasure with a configurable grace
  period, administrator cancellation, and a console command that anonymizes the account when the
  period expires.
- **Admin IP Whitelist** — restricts the admin panel to allowed IPs and CIDR ranges, with a global
  list and optional per-administrator lists.
- **Admin IP Blacklist** — a global deny-list that always wins over the whitelist, is
  identity-agnostic, and fails open when empty.
- **Admin Customer Management** — a Security section on the customer detail page: force password
  reset, block and unblock, sign out of all or individual sessions, with active-session and
  login-history tables.
- **Password Login** — a per-group switch, on by default, for classic email and password sign-in
  and registration. Switched off for a group, its login and registration forms disappear (including
  the checkout inline sign-in), its forgotten-password pages close, the admin panel creates that
  group's accounts without a password, and that group's password expiration pauses.

### Notes

- **Every feature has two switches** — the YAML configuration and the Security Settings page —
  combined with AND. Enabling one never rescues the other.
- **No Doctrine migrations are shipped.** Adding a trait to an entity or enabling a feature means
  running `doctrine:schema:update --complete --force`, or the consuming application's own migration
  workflow. See the *Installation* section of [`README.md`](README.md).
- **`twig/twig` is held below 3.29.0.** Twig 3.29.0 changed `TemplateWrapper::unwrap()` to take the
  Environment, and `sylius/mailer-bundle` v2.2.0 calls it without one, so no templated Sylius email
  can be rendered on that combination. The bound is temporary and comes off once the mailer bundle
  is fixed.
- **`3brs/enterprise-security-bundle` is pinned to `~2.3.0`** — patch releases only. The plugin
  subclasses that bundle's abstract controllers, listeners and verifiers in a hundred places, and
  its minor releases have changed abstract behaviour and removed an interface method before.

### Requirements

PHP 8.3+, Sylius 2.1 or 2.2, Symfony 6.4 or 7.4.

[1.1.0]: https://github.com/3BRS/sylius-enterprise-security-plugin/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/3BRS/sylius-enterprise-security-plugin/releases/tag/v1.0.0
