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

**Session Management** — every successful sign-in (after a user passes any 2FA or recovery-code challenge) is recorded as a row in `three_brs_customer_session` / `three_brs_admin_user_session` with the User-Agent, IP address, optional country / city, the PHP session ID, plus `created_at`, `last_activity_at`, and `revoked_at` timestamps.

- **Listing UI** — customers see their active sessions at `/{_locale}/account/sessions` (Active sessions item in the account menu); admins see them at `/admin/account/sessions` (Sessions item under Configuration). Each row shows the parsed browser + OS, IP, location, last-activity time, and a "current" marker on the row matching the request's session ID.
- **Revoke individual session** — a POST form per row marks `revoked_at` on a single record. The *current* session is intentionally non-revocable; sign out instead.
- **Revoke all other sessions** — a top-level POST flips `revoked_at` on every active record except the current one.
- **Activity tracking** — a `kernel.request` listener updates `last_activity_at` on every authenticated request, throttled to **once per 60 seconds** per session to avoid write-amplification on hot pages.
- **Revocation enforcement** — a higher-priority `kernel.request` listener checks the current request's session ID against the store on every authenticated request; if the row is `revoked_at IS NOT NULL`, the listener invalidates the PHP session, clears the security token, and redirects to the corresponding login page (or returns a 401 JSON `{"error":"session_revoked"}` for AJAX / `Accept: application/json` requests). So a revoked session signs the user out on their *next* request, no separate logout call needed. The login routes default to `sylius_shop_login` / `sylius_admin_login`; override `$customerLoginRoute` / `$adminLoginRoute` on the `SessionRevocationListener` service if you renamed them.
- **Bundled MaxMind GeoIP lookup** — plugin ships `MaxMindGeoIpLookup` ready to wire against a local GeoLite2 / GeoIP2 `.mmdb`. Other providers (IP2Location, online APIs, internal services) are pluggable via `GeoIpLookupInterface`. See [Enabling GeoIP location lookups](#enabling-geoip-location-lookups) below.
- **No entity changes required** — sessions and known devices live in their own tables and reference `ShopUser` / `AdminUser` via foreign key; no traits to add.
- **User-Agent parsing** — uses `matomo/device-detector` to extract a human-readable browser name and operating system for both the session list UI and the login-notification email body.

**Login Notifications** — on a successful sign-in, the plugin computes a fingerprint from `sha256(User-Agent + '|' + client IP)`. If that fingerprint isn't already stored in `three_brs_customer_known_device` / `three_brs_admin_user_known_device` for the user, the plugin persists it and sends a `three_brs_login_notification` email containing the time, parsed browser/OS, IP, and (if a GeoIP provider is wired up) country and city. Subsequent logins from the same UA + IP combination are treated as a known device and produce no email.

> **First-time enable on an existing installation:** the known-device table is empty when you first turn `login_notifications` on, so every active user will receive a notification email at their next sign-in (every device is "new" until it lands in the table). Expect a burst of emails right after deploy. If you want to suppress that initial wave, pre-populate `three_brs_*_known_device` rows for each `(user_id, fingerprint)` you consider trusted before flipping the switch.

### Defaults for session management & login notifications

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

The plugin adds an **Active sessions** entry to the shop account menu and a **Sessions** entry to the admin Configuration sub-menu — both shown only when session management is enabled for the relevant group.

### Enabling GeoIP location lookups

The default `GeoIpLookupInterface` binding is `NullGeoIpLookup`, which returns `null` for every lookup so the feature works out-of-the-box without any GeoIP dependency. To populate country / city in the session list and login-notification email, swap in a real provider.

The plugin ships **`MaxMindGeoIpLookup`** ready to be wired against a local MaxMind GeoLite2 / GeoIP2 `.mmdb`. To enable it:

1. **Pull in the MaxMind library** (kept under composer `suggest` so users who don't need GeoIP don't pay the dependency cost):
   ```bash
   composer require geoip2/geoip2
   ```
   Download the free `GeoLite2-City.mmdb` from [MaxMind](https://dev.maxmind.com/geoip/geolite2-free-geolocation-data) (registration required) and store it somewhere readable, e.g. `var/geoip/GeoLite2-City.mmdb`. MaxMind refreshes the database approximately twice a week, so plan a cron / CI job to re-download it.

2. **Wire the bundled service and point the config at it:**
   ```yaml
   # config/services.yaml
   services:
       ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\MaxMindGeoIpLookup:
           arguments:
               $databasePath: '%kernel.project_dir%/var/geoip/GeoLite2-City.mmdb'
   ```
   ```yaml
   # config/packages/threebrs_sylius_enterprise_security_plugin.yaml
   three_brs_sylius_enterprise_security:
       session_management:
           geoip_service: ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\MaxMindGeoIpLookup
   ```

The plugin's Extension reads `session_management.geoip_service` and replaces the default `NullGeoIpLookup` alias with your service ID — both the customer and admin trackers then call it transparently.

If you'd rather use a different provider (IP2Location, an online API, an internal service…), implement `GeoIpLookupInterface` yourself, register it as a service, and point `geoip_service` at your service ID — same swap mechanism.

> **Localhost / private IPs:** MaxMind GeoLite2 only covers public internet IPs. Lookups for `127.0.0.1`, `::1`, RFC1918 ranges (`10.x`, `172.16–31.x`, `192.168.x`) or Docker bridge networks return `null`, so the session UI will show an IP without country / city when developing locally — that's expected, not a misconfiguration.
>
> **Trusted proxies:** the device fingerprint and the stored IP both use `Request::getClientIp()`. The same trusted-proxy caveat as for rate limiting applies — without `framework.trusted_proxies` configured, all sessions appear to come from the same proxy IP and the new-device check effectively de-duplicates by User-Agent only.

### Centralized Security Settings UI

A single admin page consolidates configuration for every security feature shipped with the plugin. Administrators can change password policy, lockout thresholds, expiration days, two-factor mode and notification toggles without editing YAML or restarting the container — values are persisted in `three_brs_security_setting` and applied on the next request.

- **Admin route**: `/admin/security-settings` (item *Security settings* in the admin Configuration menu).
- **Scopes**: separate `Customers` and `Administrators` views, plus an internal `global` scope for values that have no scope dimension (2FA issuer, trusted-device window, passkey relying-party data, GeoIP service ID).
- **Storage**: one row per `(path, scope)` pair, value stored as JSON. The `SettingsProvider` reads the table once per request, in-memory cached, and falls back to YAML defaults when a row is missing — so plugins keep working out of the box and the install command is opt-in.
- **Runtime applied**: `PasswordPolicyValidator`, `PasswordHistoryValidator`, `PasswordExpirationChecker`, `PasswordChangeNotificationListener`, `TwoFactorEnforcementChecker`, lockout policies, `DynamicRateLimiterFactory`, OAuth providers (`isEnabledForCustomer/Admin` reads), `AdminSocialLoginHandler` (whitelist + locale), menu listeners and Twig extensions all read live values via `SettingsProviderInterface` / `PolicyFactoryInterface` / `FeatureToggleInterface`. Compile-time-only values (passkey `rp_id` / `rp_name`, GeoIP service ID, OAuth client secrets / Apple key paths) keep coming from YAML — they are deployment-integration plumbing, not user-facing knobs.
- **Tabs in the UI**: Password policy, Password history, Password expiration, Password change notification, Two-factor authentication, Magic link, Passkey, Account lockout, Rate limiting, Session management, Login notifications, Social login (OAuth), OAuth admin auto-registration (admin scope only).
- **Allowed / Enforced / Disabled**: the `Two-factor authentication` tab exposes the tri-state mode (`disabled` / `allowed` / `enforced`); other auth methods (Magic link, Passkey, OAuth) are login channels — they use a 2-state `enabled` toggle (`Enforced` would mean "this is the only login channel", which is a different concern handled outside this UI).
- **Fixture**: `three_brs_security_settings` (Sylius fixture) writes the YAML defaults into the table on a fresh install. By default it resets the table; set `options.reset: false` to merge instead. Per-scope `overrides` allow seeding non-default values from fixtures.

```yaml
sylius_fixtures:
    suites:
        default:
            fixtures:
                three_brs_security_settings:
                    options:
                        reset: true
                        overrides: {}
```

OAuth credentials (`client_id`, `client_secret`, Apple `team_id` / `key_id` / `private_key_path`) stay in YAML / `.env.local` — they are deployment-time secrets; putting them in the database would leak them through admin UI display, DB dumps and audit logs. The `Social login (OAuth)` UI tab exposes only the per-provider `enabled` toggle (changing it at runtime takes effect on the next OAuth attempt — providers read the flag through `SettingsProvider`). The `OAuth admin auto-registration` tab (admin scope only) holds the email-domain whitelist and the default locale assigned to admins auto-registered via OAuth — both are policy values, not secrets.

Passkey `rp_id` and `rp_name` similarly stay in YAML — the browser WebAuthn API binds registered credentials to the relying-party ID, so changing it at runtime would invalidate every passkey already registered. The GeoIP service ID is a Symfony service alias resolved at compile time; the implementation behind the alias is a deployment choice, not a runtime knob. Both surface only through YAML.

The `Linked social accounts` shop menu item and the admin Configuration *Linked social accounts* item are now gated through `FeatureToggle` against the same `oauth.google.enabled` / `oauth.apple.enabled` paths used by the providers themselves — toggling a provider off in the Settings UI hides the menu entry on the next request.

### Self-Service Account Deletion (GDPR)

Customer-driven account deletion implementing the GDPR right to erasure, with a configurable grace period and admin-side cancellation.

- **Customer-facing flow** — when enabled, the *Delete account* item appears in the shop account menu (`/{_locale}/account/delete`). The customer enters their current password (re-authentication, no email round-trip) and explicitly acknowledges the consequences. On submit:
  1. A `three_brs_customer_deletion_request` row is created with `requested_at = now`, `scheduled_for = now + grace_period_days`.
  2. The linked `ShopUser` is set to `enabled = false` immediately — login stops working at once.
  3. The customer's session is invalidated.
  4. A `three_brs_account_deletion_requested` email is sent confirming the schedule and instructing the customer to contact a store administrator if they change their mind.
- **Cancellation** — customer-initiated cancellation is intentionally NOT exposed: once submitted, the request can only be cancelled by an administrator from `/admin/account-deletions` (sub-menu under Configuration). Cancelling re-enables the `ShopUser` and stamps the request with `cancelled_at` + `cancelled_by_admin_id` for audit.
- **Grace expiry** — a console command processes due requests:
  ```
  bin/console three-brs:account-deletion:process-due
  ```
  Hook this into a cron job (every hour is fine):
  ```
  0 * * * * php /path/to/app/bin/console three-brs:account-deletion:process-due
  ```
  For each due request the command sends a `three_brs_account_deletion_completed` email **before** anonymizing (Customer.email is still live at that point), then anonymizes, then stamps `completed_at`.

**What gets anonymized** (literal interpretation of GDPR personal data: name, email, phone, address):
- `Customer.firstName` → `Deleted`, `Customer.lastName` → `User`
- `Customer.email` / `emailCanonical` → `deleted-{id}@anonymized.invalid`
- `Customer.phoneNumber` → `null`
- Every entry in the customer's address book (`sylius_address.customer_id = ...`): `firstName`, `lastName`, `street`, `city`, `postcode`, `phoneNumber` are scrubbed

**What is intentionally retained** — order rows, payment rows, order address snapshots (`sylius_order.billing_address_id` / `shipping_address_id`), 2FA secrets, recovery codes, passkey credentials, magic-link tokens, social-account links, sessions, password history. Two reasons: the spec scope is *name / email / phone / address*, and order/payment data has legitimate accounting / tax-retention requirements that take precedence over erasure. After anonymization the order browser still resolves orders to a customer row, but the row reads "Deleted User". Plugin users who need stricter erasure can layer on a project-level cleanup pass.

```yaml
three_brs_sylius_enterprise_security:
    account_deletion:
        customer:
            enabled: false
            grace_period_days: 30
```

The feature is intentionally customer-scope only — admin self-deletion is not exposed (admin lifecycle is operations-team responsibility, not GDPR self-service).

> **Cron is required.** Without `three-brs:account-deletion:process-due` running periodically, deletion requests reach `scheduled_for` but never anonymize — the customer stays disabled but their personal data lingers in the DB indefinitely.

### Admin IP Whitelist

Restrict admin panel access to a configured set of IP addresses or CIDR ranges. The feature has two layers that solve different problems:

- **Global list** — team-wide allow. Use this when everyone shares the same network: corporate LAN, VPN exit gateway, office static IP, cloud bastion CIDR. Configured under Security settings → Global → "Admin IP whitelist".
- **Per-admin list** — personal extras that don't belong in the team-wide list. Managed on `/admin/ip-whitelist/admins`. Pick an administrator and toggle their personal allow-list on/off with its own CIDR set. An admin's CIDRs stay private to that admin — they are not exposed in the global view, and they grant access only when **that specific admin** signs in. Useful when admin A occasionally signs in from a home IP that has no business being in the team-wide list (where it would also unlock admin B and the rest).

Access is granted when **either** the request IP matches the global list **or** the authenticated admin's own (enabled) list matches. A failed check returns HTTP 403 with a plain-text body — there is no redirect or login form fallback.

**Global list is mandatory when the feature is enabled.** The Security settings form rejects saving `enabled = true` with an empty global list — at least one global CIDR must be configured. This guard prevents the most common self-lockout scenario (someone flips the master switch on without filling anything in). At runtime, the post-auth check then fans out into per-admin entries on top of the global list. On `/admin/login` (anonymous, identity not yet known) the listener also lets the request through if the IP matches **any** enabled per-admin entry so an admin can reach the login form; after authentication, the post-auth check enforces that the IP matches **that specific admin's** entry (otherwise the session is rejected on the next request). This means an attacker who lands on admin A's home IP still can't sign in as admin B — the per-admin check binds the IP to the identity.

If you really want pure per-admin enforcement with no team-wide allow, add `0.0.0.0/0` and `::/0` to the global list as an explicit acknowledgement that the team-wide layer is intentionally wide open and only per-admin entries restrict access.

```yaml
three_brs_sylius_enterprise_security:
    ip_whitelist:
        enabled: false
```

#### Defaults for IP whitelist

- `ip_whitelist.enabled` defaults to `false` — the feature is off out of the box; existing admin sessions continue unchanged.
- `ip_whitelist.global_cidrs` defaults to `[]` (no IPs configured). This is GLOBAL-scope only and is edited through the Security settings UI. When the feature is enabled, the form requires at least one CIDR here.

The Configuration node only exposes the master switch. The actual allow-lists live in the database (DB-backed settings + a per-admin entity, `three_brs_admin_user_ip_whitelist`) so that operators can change them at runtime without redeploying.

> **Operator note.** The UI guard above prevents enabling the feature with an empty global list, so the standard self-lockout path is closed. If the feature still gets misconfigured (e.g. via direct DB edits) and the global list no longer matches your IP, disable the feature via SQL to recover: `UPDATE three_brs_security_setting SET value = 'false' WHERE scope = 'global' AND path = 'ip_whitelist.enabled'`. CIDR validation accepts both IPv4 (e.g. `10.0.0.0/8`, `192.168.1.1`) and IPv6 (e.g. `2001:db8::/32`, `::1`).

#### When IP whitelist is the right tool

This feature is network-bound — it only helps when the admins reach the panel from a predictable IP range. Use it when administrators sit on a corporate LAN with a known public IP, connect through a VPN that exits to a fixed CIDR, or when the admin host is itself a cloud VM with a static address.

It's **not** the right control when admins log in from rotating home IPs (PPPoE / DHCP), mobile data behind CG-NAT, or arbitrary travel networks — they will lock themselves out the moment their ISP rotates the lease. In those cases, leave the master switch off and rely on the device-/possession-bound controls already shipped in this plugin: 2FA + passkeys + account lockout + rate limiting. Those follow the user instead of the network, so a changing IP doesn't break them. IP whitelist is defense-in-depth on top of fixed-network setups, not a replacement for those factors.

If you're behind a reverse proxy or load balancer, configure Symfony's `framework.trusted_proxies` so that `Request::getClientIp()` returns the real client IP rather than the proxy address — otherwise the listener compares the proxy IP against your CIDR list and you'll either let everyone in or lock everyone out.

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
