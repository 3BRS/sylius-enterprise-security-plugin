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

### Social Login (OAuth)

- Google and Apple OAuth sign-in for shop customers and admin users, independently configurable per group
- Extensible provider abstraction (`OAuthProviderInterface` + registry) — additional providers (Facebook, Microsoft, …) can be added by implementing a single interface and tagging with `three_brs.oauth_provider`
- Social accounts are stored as separate link entities (`three_brs_customer_social_account_link`, `three_brs_admin_user_social_account_link`) — multiple providers per user supported
- Email-match flow: when an OAuth identity's email matches an existing local account, the user is prompted to confirm their password before the link is created (prevents account takeover)
- Auto-registration: if the email is unknown, a new account is created and the social identity linked automatically. For admin users, auto-registration creates accounts with `ROLE_ADMINISTRATION_ACCESS` — access can be revoked by another admin disabling the user
- Link/unlink from the account page. A `LastAuthMethodGuard` refuses to unlink the last remaining sign-in method (password or another social link)
- Apple specifics handled: JWT ES256 `client_secret` generated at runtime from `team_id`/`key_id`/private key, `form_post` callback, first-auth-only name persisted, private relay emails accepted as-is
- Fixture (`three_brs_social_account_link`) to preload social links for demo/testing

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

> **Admin auto-registration:** by default `auto_register_allowed_email_domains` is empty and admin auto-registration is **disabled** — an unknown OAuth identity hitting the admin login will be rejected. Add your corporate domain(s) to opt in. Auto-created admins receive `ROLE_ADMINISTRATION_ACCESS` and the configured `default_locale`.

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
