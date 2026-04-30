<p align="center">
    <a href="https://www.3brs.com" target="_blank">
        <img src="https://3brs1.fra1.cdn.digitaloceanspaces.com/3brs/logo/3BRS-logo-sylius-200.png"/>
    </a>
</p>
<h1 align="center">
    Enterprise Security Plugin
    <br />
    <a href="https://packagist.org/packages/3brs/sylius-enterprise-security-plugin" title="License" target="_blank">
        <img src="https://img.shields.io/packagist/l/3brs/sylius-enterprise-security-plugin.svg" />
    </a>
    <a href="https://packagist.org/packages/3brs/sylius-enterprise-security-plugin" title="Version" target="_blank">
        <img src="https://img.shields.io/packagist/v/3brs/sylius-enterprise-security-plugin.svg" />
    </a>
    <a href="https://github.com/3BRS/sylius-enterprise-security-plugin/actions" title="Build status" target="_blank">
        <img src="https://github.com/3BRS/sylius-enterprise-security-plugin/actions/workflows/ci.yml/badge.svg" />
    </a>
</h1>

## Repository Structure

This is a monorepo containing two packages:

```
sylius-enterprise-security-plugin/
├── packages/
│   └── enterprise-security-bundle/
│       ├── src/
│       └── composer.json
├── src/
├── tests/
│   └── Application/
└── composer.json
```

### `3brs/sylius-enterprise-security-plugin`

Sylius-specific plugin. Contains Doctrine entity extensions for `ShopUser` and `AdminUser`, UI controllers, Behat test suite, and Sylius fixture integration.

### `3brs/enterprise-security-bundle`

Standalone Symfony bundle with no dependency on Sylius. Contains reusable interfaces, services, and event listeners that can be used independently of Sylius (e.g. in a plain Symfony app).

## Features

### Password Policy

- Configurable minimum and maximum password length (overrides Sylius's default 3-character minimum)
- Complexity requirements: uppercase, lowercase, numbers, and special characters — each independently toggleable
- Core validation logic implemented as a reusable Symfony validator in `enterprise-security-bundle` (no Sylius dependency)
- Sylius plugin layer applies the policy to `ShopUser` (customer) and `AdminUser` entities with separate configuration for each

### Defaults for password policy

```yaml
three_brs_sylius_enterprise_security:
    password_policy:
        customer:
            min_length: 8
            max_length: ~
            require_uppercase: false
            require_lowercase: false
            require_numbers: false
            require_special_characters: false

        admin:
            min_length: 12
            max_length: ~
            require_uppercase: true
            require_lowercase: true
            require_numbers: true
            require_special_characters: true
```

### Password History

- Prevents users from reusing recent passwords
- Configurable number of previous passwords to remember per user type
- Separate history tables for customers (`three_brs_customer_password_history`) and admins (`three_brs_admin_user_password_history`)

### Defaults for password history

```yaml
three_brs_sylius_enterprise_security:
    password_history:
        customer:
            enabled: false
            count: 5
        admin:
            enabled: false
            count: 10
```

### Password Expiration

- Forces password change after a configurable number of days
- Supports `force_change` flag to immediately require a password change on next login
- Admin users are redirected to a dedicated change-password page; shop users to the standard change-password flow
- Configurable independently for customers and admins

### Defaults for password expiration

```yaml
three_brs_sylius_enterprise_security:
    password_expiration:
        customer:
            enabled: false
            days: 90
        admin:
            enabled: false
            days: 60
```

### Password Change Notifications

- Sends an email notification whenever a user's password is changed
- Covers all flows: account settings change, forgot-password reset, admin-forced change, and admin editing another user's password
- Detection is Doctrine-based — the listener catches password updates at flush time regardless of which flow triggered them
- Email contains timestamp, IP address (when available), and a secure-account link when the change was not initiated by the user
- `initiatedByUser` is derived from the current security token: when the authenticated user matches the user whose password changed, the secure-account link is omitted
- Configurable independently for customers and admins (enable/disable)

```yaml
three_brs_sylius_enterprise_security:
    password_change_notification:
        customer:
            enabled: false
        admin:
            enabled: false
```

> **Note (reverse proxy / load balancer):** the IP address included in the email is read from `Request::getClientIp()`, which respects `X-Forwarded-For` only for trusted proxies. If your Sylius runs behind a load balancer or reverse proxy, make sure `framework.trusted_proxies` and `framework.trusted_headers` are configured (e.g. via the `TRUSTED_PROXIES` / `TRUSTED_HEADERS` environment variables) — otherwise the email will log the proxy's address instead of the real client IP. See the [Symfony docs on trusted proxies](https://symfony.com/doc/current/deployment/proxies.html).

### Two-Factor Authentication

- TOTP-based 2FA for shop and admin users (compatible with Google Authenticator, Authy, 1Password, etc.)
- QR code + manual secret setup from account page (shop) or admin dashboard (admin)
- Recovery codes — single-use backup codes generated at setup, regenerable from the manage view (invalidates all previous codes)
- Trusted device — opt-in cookie (scheb JWT) to skip 2FA on a known device; revocable per user by bumping the user's `trustedTokenVersion`
- Enforcement modes per user type: `disabled`, `optional`, `enforced`. In `enforced` mode a user without 2FA is redirected to the setup page until they enable it
- Firewall integration via `scheb/2fa-bundle` with separate `/2fa` (shop) and `/admin/2fa` (admin) challenge endpoints
- Fixture (`three_brs_two_factor`) to preload 2FA-enabled users and recovery codes for demo/testing
- Plugin exposes container parameters (`three_brs.two_factor.issuer`, `three_brs.two_factor.trusted_device_enabled`, `three_brs.two_factor.trusted_device_lifetime`) that can be referenced directly from your `scheb_2fa.yaml`

```yaml
three_brs_sylius_enterprise_security:
    two_factor_authentication:
        issuer: 'Sylius'
        customer:
            mode: 'optional'  # disabled | optional | enforced
        admin:
            mode: 'enforced'
        recovery_codes:
            customer:
                enabled: true
                count: 8
            admin:
                enabled: true
                count: 8
        trusted_device:
            enabled: true
            days: 60
```

`trusted_device` is global (scheb-wide) and shared between shop and admin firewalls — scheb's JWT-cookie trusted-device implementation supports only a single lifetime.

```yaml
# config/packages/scheb_2fa.yaml
scheb_two_factor:
    trusted_device:
        enabled: '%three_brs.two_factor.trusted_device_enabled%'
        lifetime: '%three_brs.two_factor.trusted_device_lifetime%'
        key: '%env(THREE_BRS_TWO_FACTOR_TRUSTED_DEVICE_KEY)%' # required, >=256-bit secret for JWT HMAC-SHA256
    totp:
        issuer: '%three_brs.two_factor.issuer%'
```

On the **shop firewall**, replace Sylius' default `form_login.success_handler` (`sylius.authentication.success_handler`) with the plugin's 2FA-aware wrapper. The default Sylius handler returns a `JsonResponse` on XHR and redirects straight to the target path without checking for a `TwoFactorTokenInterface`, which produces a broken UX during 2FA challenges:

```yaml
# config/packages/security.yaml
security:
    firewalls:
        shop:
            form_login:
                success_handler: ThreeBRS\SyliusEnterpriseSecurityPlugin\Security\TwoFactorAwareAuthenticationSuccessHandler.shop
            two_factor:
                auth_form_path: /2fa
                check_path: /2fa_check
                prepare_on_login: true
                prepare_on_access_denied: true
        admin:
            two_factor:
                auth_form_path: /admin/2fa
                check_path: /admin/2fa_check
                prepare_on_login: true
                prepare_on_access_denied: true
```

The admin firewall does not need a custom `success_handler` — Sylius does not override it there, so the default Symfony handler is used and scheb's `TwoFactorAccessListener` transparently redirects authenticated-but-not-yet-verified admins to `/admin/2fa` on the next request.

### Social Login (OAuth)

- **Google and Apple sign-in** for shop customers and admin users — sign-in buttons are rendered on the shop login + register pages and on the admin login page
- **Independent shop/admin configuration** — each provider is enabled and configured separately for the shop and admin groups, so you can register two distinct OAuth clients (different client IDs, consent screens, redirect URIs). Useful when the shop-facing app and the internal admin app live as separate applications on the provider side
- **Three callback flows** depending on what the plugin finds for the OAuth identity's email:
  - existing linked account → straight log-in
  - email matches a local account → password confirmation prompt before the link is created (prevents account takeover)
  - email is unknown → a new account is auto-registered and the social identity linked (admin auto-registration is gated by an email-domain whitelist; see below)
- **Multiple providers per user** — links live in dedicated entities (`three_brs_customer_social_account_link`, `three_brs_admin_user_social_account_link`)
- **Link / unlink from the account page** — `LastAuthMethodGuard` refuses to unlink the last remaining sign-in method (password or another social link), so a user can never lock themselves out
- **Extensible provider registry** — add Facebook, Microsoft, GitHub, … without forking the plugin. Implement `OAuthProviderInterface` (`getName`, `isEnabledForCustomer`, `isEnabledForAdmin`, `getAuthorizationUrl`, `fetchUserInfo`) and tag the service with `three_brs.oauth_provider`. `OAuthProviderRegistry` collects every tagged provider and the login controllers / Twig templates pick them up automatically — no routing, controller or template changes needed. `fetchUserInfo()` returns an `OAuthUserInfoInterface` (email, first/last name, provider user ID, email-verified flag) used uniformly across the link / register / login flow
- **Apple specifics handled** — JWT ES256 `client_secret` generated at runtime from `team_id` / `key_id` / private key, `form_post` callback, first-auth-only name persisted, private relay emails accepted as-is
- **Fixture** (`three_brs_social_account_link`) to preload social links for demo/testing

```yaml
three_brs_sylius_enterprise_security:
    oauth:
        customer:
            google:
                enabled: false
                client_id: '%env(GOOGLE_CLIENT_ID)%'
                client_secret: '%env(GOOGLE_CLIENT_SECRET)%'
            apple:
                enabled: false
                client_id: '%env(APPLE_CLIENT_ID)%'
                team_id: '%env(APPLE_TEAM_ID)%'
                key_id: '%env(APPLE_KEY_ID)%'
                private_key_path: '%kernel.project_dir%/config/secrets/apple_private_key.p8'
        admin:
            default_locale: 'en_US'                    # locale assigned to auto-registered admins
            auto_register_allowed_email_domains: []    # empty = auto-registration disabled; add e.g. ['yourcompany.com']
            google:
                enabled: false
                client_id: '%env(GOOGLE_ADMIN_CLIENT_ID)%'
                client_secret: '%env(GOOGLE_ADMIN_CLIENT_SECRET)%'
            apple:
                enabled: false
                client_id: '%env(APPLE_ADMIN_CLIENT_ID)%'
                team_id: '%env(APPLE_TEAM_ID)%'
                key_id: '%env(APPLE_ADMIN_KEY_ID)%'
                private_key_path: '%kernel.project_dir%/config/secrets/apple_admin_private_key.p8'
```

Callback URLs to register with the providers:

- Shop: `https://<your-domain>/oauth/{provider}/callback`
- Admin: `https://<your-domain>/admin/oauth/{provider}/callback`

> **Admin auto-registration:** by default `auto_register_allowed_email_domains` is empty and admin auto-registration is **disabled** — an unknown OAuth identity hitting the admin login is rejected. Add your corporate domain(s) to opt in. Auto-created admins receive `ROLE_ADMINISTRATION_ACCESS` and the configured `default_locale`.
>
> **Warning:** the `allowed_email_domains` whitelist should include **only domains you fully control**.
> Anyone with a working email in these domains can auto-create an admin account with full `ROLE_ADMINISTRATION_ACCESS`.
> For external/shared domains or when fine-grained control is needed, leave the whitelist empty — admins will need to be created manually before their first OAuth login.

#### Google Cloud setup

1. Open the [Google Cloud Console](https://console.cloud.google.com/) and create (or select) a project.
2. **APIs & Services → OAuth consent screen** — choose *External*, fill in the app name, support email and developer contact. Add the scopes `openid`, `email`, `profile`. Add test users while the app is in *Testing* mode.
3. **APIs & Services → Credentials → Create credentials → OAuth client ID**:
   - Application type: *Web application*
   - Authorized JavaScript origins: `https://<your-domain>`
   - Authorized redirect URIs: `https://<your-domain>/oauth/google/callback` (shop) and/or `https://<your-domain>/admin/oauth/google/callback` (admin)
4. Copy the generated **Client ID** and **Client secret** into your `.env.local`:
   ```dotenv
   GOOGLE_CLIENT_ID=...
   GOOGLE_CLIENT_SECRET=...
   GOOGLE_ADMIN_CLIENT_ID=...
   GOOGLE_ADMIN_CLIENT_SECRET=...
   ```
   Shop and admin can share a single OAuth client, but separate clients are recommended so you can revoke/rotate them independently.
5. Flip `enabled: true` for the relevant group in `threebrs_sylius_enterprise_security_plugin.yaml`.

#### Apple Developer setup

Apple Sign In requires a paid Apple Developer account and a **public HTTPS** redirect URL — `http://localhost` is not accepted. For local testing expose your dev host over HTTPS (ngrok, Cloudflare Tunnel, …).

1. In the [Apple Developer portal](https://developer.apple.com/account/resources/) → **Certificates, Identifiers & Profiles**:
   - **Identifiers → App IDs → +** — create an App ID, enable the *Sign In with Apple* capability.
   - **Identifiers → Services IDs → +** — create a Services ID (this becomes the `client_id`), enable *Sign In with Apple*, configure the primary App ID and add your return URL: `https://<your-domain>/oauth/apple/callback` (and/or the admin variant).
   - **Keys → +** — create a key with *Sign In with Apple* enabled, associate it with the primary App ID, download the `.p8` private key. **The file is only downloadable once.** Note the **Key ID**.
2. Find your **Team ID** in the top-right of the Apple Developer portal (or under *Membership*).
3. Store the private key inside the project (outside of version control) and set env vars:
   ```dotenv
   APPLE_CLIENT_ID=com.yourcompany.sylius.signin       # the Services ID
   APPLE_TEAM_ID=ABCDE12345
   APPLE_KEY_ID=FGHIJ67890
   # path is configured in yaml: %kernel.project_dir%/config/secrets/apple_private_key.p8
   ```
4. Flip `enabled: true` for the relevant group. The plugin generates Apple's ES256 `client_secret` JWT at runtime — you don't store a long-lived secret.

### Magic Link Login

- Passwordless sign-in for shop customers and admin users, independently configurable per group
- User submits their email, the plugin generates a single-use token, stores only its SHA-256 hash, and emails a link (`/magic-link/verify/{token}` for shop, `/admin/magic-link/verify/{token}` for admin)
- Following the link signs the user in and marks the token as used — one-time use only
- Separate link (like "Forgotten password?") is rendered on the shop and admin login pages via Sylius twig hooks; no markup changes required in your theme
- Tokens live in dedicated tables (`three_brs_customer_magic_link_token`, `three_brs_admin_user_magic_link_token`) — only hashes are stored, plain tokens exist only in the email
- Anti-enumeration: the request endpoint always responds with the same neutral confirmation whether the email is known, unknown, disabled, or rate-limited — no information about account existence leaks
- Timing-attack mitigation: every code path is padded to a fixed wall-clock deadline (`DeadlineTimingPadding`, default 2 s) so response time does not leak account existence either — known/unknown/rate-limited requests all return at the same time. The 2-second default is chosen to comfortably cover the slowest happy path (DB write + SMTP send) on typical infrastructure; tune it by decorating the `ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\DeadlineTimingPadding` service with a different `$targetSeconds` if your SMTP transport is faster or slower than that
- Rate limiting per user: configurable count within a sliding window (defaults to 3 requests / 15 minutes)
- 2FA-aware: if the authenticated user has `scheb/2fa` enabled, the verify controller dispatches `AuthenticationTokenCreatedEvent` on the firewall event dispatcher so scheb wraps the token and redirects to the 2FA challenge — the magic link does **not** bypass the second factor
- Fixture (`three_brs_magic_link`) to preload tokens for demo/testing

```yaml
three_brs_sylius_enterprise_security:
    magic_link:
        customer:
            enabled: false
            expiration_seconds: 300      # 5 minutes
        admin:
            enabled: false
            expiration_seconds: 300
```

> Magic-link rate limiting (default 3 requests / 15 minutes) is configured separately via the centralized `rate_limit.{customer,admin}.magic_link.{enabled,limit,interval}` block — see [Account Lockout & Rate Limiting](#account-lockout--rate-limiting) below.

Expose the request and verify endpoints as public in your firewall access control (the verify controller authenticates internally):

```yaml
# config/packages/security.yaml
security:
    access_control:
        - { path: ^/magic-link$, role: PUBLIC_ACCESS }
        - { path: ^/magic-link/verify/, role: PUBLIC_ACCESS }
        - { path: "%sylius.security.admin_regex%/magic-link$", role: PUBLIC_ACCESS }
        - { path: "%sylius.security.admin_regex%/magic-link/verify/", role: PUBLIC_ACCESS }
```

### Passkey Login (WebAuthn / FIDO2)

- Passwordless sign-in for shop customers and admin users using passkeys (platform authenticators like Touch ID / Windows Hello / Android lock, or hardware security keys such as YubiKey). Independently configurable per group.
- Multiple passkeys per user — labelled (e.g. "MacBook Touch ID", "YubiKey") so the user can identify them. Managed at `/account/passkey` (shop) and `/admin/account/passkey` (admin).
- Per-user credential storage in dedicated tables (`three_brs_customer_passkey_credential`, `three_brs_admin_user_passkey_credential`) — credential ID, public key, sign counter and other metadata serialized as JSON.
- Built on `web-auth/webauthn-lib` — server-side challenge generation and assertion verification follow the standard WebAuthn ceremony.
- 2FA-aware (default safe): the verify controller dispatches `AuthenticationTokenCreatedEvent` on the firewall event dispatcher so scheb wraps the token and redirects to the 2FA challenge — passkeys do **not** bypass the second factor by default.
- Optional UV bypass: if `passkey.skip_2fa_when_user_verified: true`, passkeys with the `userVerified` flag set (i.e. authenticator required biometrics or PIN) are accepted as multi-factor on their own and skip the scheb 2FA challenge.
- Last-auth-method protection: the existing `LastAuthMethodGuard` is extended to count passkeys, social links and password together; the user cannot remove the last sign-in method on their account.
- Frontend JavaScript (`bundles/threebrssyliusenterprisesecurityplugin/js/passkey.js`) handles `navigator.credentials.create()` / `get()` and the JSON dance with the server. Browsers without the WebAuthn API see a hidden / disabled UI instead of a broken button.
- Sylius twig hooks render a "Sign in with a passkey" button on the shop and admin login pages — no theme changes required.
- Plugin adds a **Passkeys** entry to the shop account menu (`sylius.menu.shop.account`) and to the admin **Configuration** sub-menu (`sylius.menu.admin.main`) automatically — both shown only when the feature is enabled for that group.
- Fixture (`three_brs_passkey`) to preload placeholder credentials for demo/testing of list/remove flows.
- **End-to-end Behat coverage without a real browser** — the `passkey_ceremony` suite runs a PHP-side authenticator emulator (`tests/Behat/Service/Passkey/FakeAuthenticator`) that generates real ES256 keypairs in PHP, signs assertions and serializes WebAuthn structures (CBOR + COSE) exactly as a real authenticator would. Server-side `web-auth/webauthn-lib` validates the signed payloads end-to-end, so the full register / login ceremony is covered without Selenium, Panther or a browser. Run with `APP_ENV=test ./bin-docker/php vendor/bin/behat --suite=shop_passkey_ceremony` (and the admin variant). Note: this layer does **not** exercise the JavaScript glue in `passkey.js`; that is left for a separate JS unit-test suite if/when added.

### Defaults for passkey

```yaml
three_brs_sylius_enterprise_security:
    passkey:
        rp_id: ~
        rp_name: ~
        skip_2fa_when_user_verified: false
        customer:
            enabled: false
        admin:
            enabled: false
```

### Required configuration to enable passkeys

`rp_id` and `rp_name` are `null` by default and must be set before passkeys can be used — registration and login will silently fail otherwise. Minimum config to turn the feature on for shop customers:

```yaml
three_brs_sylius_enterprise_security:
    passkey:
        rp_id: example.com               # your domain (or `localhost` in dev)
        rp_name: 'My Sylius Shop'        # display name shown by the browser
        customer:
            enabled: true
```

Expose the passkey login endpoints as public in your firewall access control (the verify controller authenticates internally):

```yaml
# config/packages/security.yaml
security:
    access_control:
        - { path: ^/passkey/login/options$, role: PUBLIC_ACCESS }
        - { path: ^/passkey/login/verify$, role: PUBLIC_ACCESS }
        - { path: "%sylius.security.admin_regex%/passkey/login/options$", role: PUBLIC_ACCESS }
        - { path: "%sylius.security.admin_regex%/passkey/login/verify$", role: PUBLIC_ACCESS }
```

After installing the plugin, run `bin/console assets:install` so the bundled `passkey.js` is symlinked into your `public/bundles/` directory.

> **Deployment / HTTPS:** the WebAuthn browser API only works over HTTPS or `http://localhost`. Ensure your production deployment is reachable over TLS — registration and login will silently fail otherwise.
>
> **`rp_id` must match the host the user is on.** For e.g. `https://shop.example.com`, `rp_id` should be `shop.example.com` (or `example.com` if you want passkeys to work on subdomains too). A mismatch causes silent registration / login failures in the browser.

### Account Lockout & Rate Limiting

Brute-force protection covering both account-level lockout (persistent, per user) and request-level rate limiting (ephemeral, keyed per username for login and per IP for other actions). Independently configurable per group (customer / admin).

**Account Lockout** — locks the user account after a configurable number of consecutive failed sign-in attempts.

- Failed-attempts counter and lockout timestamps tracked on the user entity via `LockableShopUserTrait` / `LockableAdminUserTrait` (concurrent failed logins are serialised through a pessimistic row lock so the threshold cannot be bypassed)
- Counter resets on successful login
- **Auto-unlock** after `auto_unlock_after` seconds (set to `null` for manual-only)
- **Manual unlock** by admin from `/admin/locked-customers` and `/admin/locked-admins` (sub-menu under Configuration). The list shows both the absolute auto-unlock timestamp (*Auto-unlock at*) and the relative countdown (*Time remaining*) for each locked account.
- Both unlock methods can coexist — auto-unlock fires first when `lockoutUntil` is reached, admin can override manually any time
- Locked sign-in attempts get the same generic *"Invalid credentials"* response as a wrong-password attempt — by design, so account state does not leak through error text

**Rate Limiting** — built on Symfony Rate Limiter (`fixed_window` policy). Plugin auto-registers `framework.rate_limiter.three_brs_<group>_<action>` services for every enabled combination, no manual `framework.yaml` wiring needed.

Throttled endpoints:

| Action | Customer | Admin |
|---|---|---|
| Login | ✓ | ✓ |
| Password reset | ✓ | ✓ |
| Register | ✓ | — *(admin has no self-registration)* |
| Magic link | ✓ | ✓ |

When the limit is exceeded the user is redirected back to the form with a `three_brs.rate_limit.too_many_requests` error flash.

> **Admin manual unlock:** clicking *Unlock* on a locked account clears the DB lockout state and the login rate-limit counter for that user, so they can sign in immediately.

```yaml
three_brs_sylius_enterprise_security:
    account_lockout:
        customer:
            enabled: false
            max_attempts: 5
            auto_unlock_after: ~        # seconds; ~ (default) means manual-unlock-only
        admin:
            enabled: false
            max_attempts: 3
            auto_unlock_after: ~
    rate_limit:
        customer:
            login:           { enabled: false, limit: 5, interval: '15 minutes' }
            password_reset:  { enabled: false, limit: 3, interval: '1 hour' }
            register:        { enabled: false, limit: 5, interval: '1 hour' }
            magic_link:      { enabled: false, limit: 3, interval: '15 minutes' }
        admin:
            login:           { enabled: false, limit: 5, interval: '15 minutes' }
            password_reset:  { enabled: false, limit: 3, interval: '1 hour' }
            magic_link:      { enabled: false, limit: 3, interval: '15 minutes' }
```

Add the lockout fields to your `ShopUser` and `AdminUser` entities (same pattern as 2FA / password expiration):

```php
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\LockableShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\LockableShopUserTrait;

class ShopUser extends BaseShopUser implements LockableShopUserInterface
{
    use LockableShopUserTrait;
}
```

The trait adds four columns (`failed_login_attempts`, `last_failed_login_at`, `locked_at`, `lockout_until`); run a schema update after adding the trait, e.g. `bin/console doctrine:schema:update --complete --force` or your usual migration workflow.

Plugin adds **Locked customers** and **Locked administrators** entries to the admin Configuration sub-menu automatically — both shown only when lockout is enabled for that group.

> **Trusted proxies:** for password reset, registration, and magic-link rate limits the key is `Request::getClientIp()` (login rate limits use the submitted username so admin unlock can clear them deterministically). If your Sylius runs behind a load balancer or reverse proxy, configure `framework.trusted_proxies` and `framework.trusted_headers` so the real client IP is used — otherwise all non-login requests look like they come from the proxy and the limit triggers immediately.

### Session Management & Login Notifications

Active session listing with manual revocation, plus optional email notifications when a user signs in from a previously unseen device. Independently configurable per group (customer / admin).

**Session Management** — every successful sign-in (after a user passes any 2FA or recovery-code challenge) is recorded as a row in `three_brs_customer_session` / `three_brs_admin_user_session` with the User-Agent, IP address, optional country/city (from a pluggable GeoIP provider), the PHP session ID, plus `created_at`, `last_activity_at`, and `revoked_at` timestamps.

- **Listing UI** — customers see their active sessions at `/{_locale}/account/sessions` (Active sessions item in the account menu); admins see them at `/admin/account/sessions` (Sessions item under Configuration). Each row shows the parsed browser + OS, IP, location, last-activity time, and a "current" marker on the row matching the request's session ID.
- **Revoke individual session** — a POST form per row marks `revoked_at` on a single record (the *current* session is intentionally non-revocable from the list to avoid users locking themselves out by accident; sign out instead).
- **Revoke all other sessions** — a top-level POST flips `revoked_at` on every active record except the current one.
- **Activity tracking** — a `kernel.request` listener updates `last_activity_at` on every authenticated request, throttled to **once per 60 seconds** per session to avoid write-amplification on hot pages.
- **Revocation enforcement** — a higher-priority `kernel.request` listener checks the current request's session ID against the store on every authenticated request; if the row is `revoked_at IS NOT NULL`, the listener invalidates the PHP session, clears the security token, and redirects to the corresponding login page. So a revoked session signs the user out on their *next* request, no separate logout call needed.

**Login Notifications** — on a successful sign-in, the plugin computes a fingerprint from `sha256(User-Agent + '|' + client IP)`. If that fingerprint isn't already stored in `three_brs_customer_known_device` / `three_brs_admin_user_known_device` for the user, the plugin persists it and sends a `three_brs_login_notification` email containing the time, parsed browser/OS, IP, and (if a GeoIP provider is wired up) country and city. Subsequent logins from the same UA + IP combination are treated as a known device and produce no email.

**GeoIP integration** — pluggable via `ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\GeoIpLookupInterface`. The default binding is `NullGeoIpLookup`, which returns `null` for every lookup (no hard dependency on MaxMind, no DB downloads). To plug in a real provider:

1. **Add a GeoIP library** (the plugin doesn't ship one to keep the dependency footprint small). Typical pick is `composer require geoip2/geoip2`; download the free `GeoLite2-City.mmdb` from [MaxMind](https://dev.maxmind.com/geoip/geolite2-free-geolocation-data) and store it somewhere readable, e.g. `var/geoip/GeoLite2-City.mmdb`.

2. **Implement the interface** in your application:
   ```php
   namespace App\Service;

   use GeoIp2\Database\Reader;
   use GeoIp2\Exception\AddressNotFoundException;
   use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\GeoIpLookupInterface;
   use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\GeoIpResult;

   class MaxMindGeoIpLookup implements GeoIpLookupInterface
   {
       public function __construct(protected string $databasePath) {}

       public function lookup(?string $ipAddress): ?GeoIpResult
       {
           if ($ipAddress === null || $ipAddress === '') {
               return null;
           }
           try {
               $record = (new Reader($this->databasePath))->city($ipAddress);
               return new GeoIpResult($record->country->isoCode, $record->city->name);
           } catch (AddressNotFoundException) {
               return null;
           }
       }
   }
   ```

3. **Register the service and point the config at it:**
   ```yaml
   # config/services.yaml
   services:
       App\Service\MaxMindGeoIpLookup:
           arguments:
               $databasePath: '%kernel.project_dir%/var/geoip/GeoLite2-City.mmdb'
   ```
   ```yaml
   # config/packages/threebrs_sylius_enterprise_security_plugin.yaml
   three_brs_sylius_enterprise_security:
       session_management:
           geoip_service: App\Service\MaxMindGeoIpLookup   # service ID
   ```

The plugin's Extension reads `session_management.geoip_service` and replaces the default `NullGeoIpLookup` alias with your service ID — both the customer and admin trackers then call it transparently. For local development the plugin ships a `FakeGeoIpLookup` (`tests/Application/src/Service/FakeGeoIpLookup.php`, wired in `services_dev.yaml`) that maps Docker bridge / RFC5737 ranges to canned city names so the Active Sessions UI is populated when clicking around the dev shop without a real MaxMind DB.

**User-Agent parsing** — uses `matomo/device-detector` to extract a human-readable browser name and operating system for both the session list UI and the login-notification email body.

```yaml
three_brs_sylius_enterprise_security:
    session_management:
        geoip_service: ~          # service ID of a GeoIpLookupInterface implementation, or null for no GeoIP
        customer:
            enabled: false
        admin:
            enabled: false
    login_notifications:
        customer:
            enabled: false
        admin:
            enabled: false
```

Defaults (cross-checked against `Configuration.php`):

- `session_management.geoip_service`: **`null`** (no GeoIP lookups; UI shows `—` for country/city)
- `session_management.customer.enabled`: **`false`**
- `session_management.admin.enabled`: **`false`**
- `login_notifications.customer.enabled`: **`false`**
- `login_notifications.admin.enabled`: **`false`**

No entity changes are required on `ShopUser` or `AdminUser` — sessions and known devices live in their own tables and reference the user via foreign key. Plugin adds an **Active sessions** entry to the shop account menu and a **Sessions** entry to the admin Configuration sub-menu automatically — both shown only when session management is enabled for that group.

> **Trusted proxies:** the device fingerprint and the stored IP both use `Request::getClientIp()`. The same trusted-proxy caveat as for rate limiting applies — without `framework.trusted_proxies` configured, all sessions appear to come from the same proxy IP and the new-device check effectively de-duplicates by User-Agent only.

## Installation

1. Run `composer require 3brs/sylius-enterprise-security-plugin`.

1. Add plugin and bundle to your `config/bundles.php`:

   ```php
   return [
       // ...
       ThreeBRS\EnterpriseSecurityBundle\ThreeBRSEnterpriseSecurityBundle::class => ['all' => true],
       ThreeBRS\SyliusEnterpriseSecurityPlugin\ThreeBRSSyliusEnterpriseSecurityPlugin::class => ['all' => true],
   ];
   ```

1. Import plugin configuration by creating `config/packages/threebrs_sylius_enterprise_security_plugin.yaml`:

   ```yaml
   imports:
       - { resource: "@ThreeBRSSyliusEnterpriseSecurityPlugin/Resources/config/config.yaml" }
   ```

## Development

### Usage

- Develop your plugin in `/src`
- See [`bin/`](./bin) and [`Makefile`](./Makefile) for useful commands

### Bootstrapping the dev environment

Spin up the dockerized test application (DB, PHP, assets, migrations, fixtures wiring) in one go:

```bash
make init
```

This builds the containers, runs `composer install`, creates the database, applies all migrations (including the plugin's `three_brs_*_social_account_link` tables) and builds the frontend assets. Use `make init-tests` for the `test` environment.

In the dev environment both Google and Apple OAuth providers are swapped for a fake in-memory provider (see [`tests/Application/config/services_dev.yaml`](./tests/Application/config/services_dev.yaml)) so the social-login buttons work end-to-end without any external credentials. To exercise the real Google/Apple flows locally, comment out the `FakeOAuthProvider` override and fill in your credentials in `tests/Application/.env.local`.

### Testing

After your changes you must ensure that the tests are still passing.

```bash
make ci
```

## License

MIT License. See [LICENSE](./LICENSE) for details.

## Credits

Developed by [3BRS](https://3brs.com)
