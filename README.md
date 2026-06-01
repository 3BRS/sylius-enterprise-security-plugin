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

## 3BRS Enterprise Security

- Password Policy
- Password History
- Password Expiration
- Password Change Notifications
- Two-Factor Authentication
- 3rd-party OAuth (Social Login)
- Magic Link Login
- Passkey Login (WebAuthn / FIDO2)
- Account Lockout & Rate Limiting
- Session Management & Login Notifications
- Centralized Security Settings UI
- Self-Service Account Deletion (GDPR)
- Admin IP Whitelist
- Admin IP Blacklist
- Admin Customer Management
- Per-User Password Login Control

<p align="center"><img src="docs/images/two-factor-setup.png" alt="Two-factor authentication setup with QR code and recovery secret" width="1000" /></p>

<p align="center"><img src="docs/images/active-sessions.png" alt="Active sessions list with per-device sign-out and revocation" width="1000" /></p>

<p align="center"><img src="docs/images/customer-security-section.png" alt="Admin customer Security section — force password reset, block, password-login toggle, sessions and login history" width="1000" /></p>

<p align="center"><img src="docs/images/security-settings.png" alt="Centralized Security Settings admin UI — password policy, history and rate limiting" width="1000" /></p>

## Features

### Password Policy

Enforces configurable password complexity (minimum/maximum length and uppercase / lowercase / number / special-character requirements) for shop customers and admin users, configured separately per group. Overrides Sylius's weak 3-character default.

To learn more read [password-policy.md](docs/password-policy.md).

### Password History

Prevents users from reusing their recent passwords, with a configurable number of previous passwords remembered per group. Customer and admin history are stored separately.

To learn more read [password-history.md](docs/password-history.md).

### Password Expiration

Forces a password change after a configurable number of days, with an optional `force_change` flag to require it on next login. Configurable independently for customers and admins.

To learn more read [password-expiration.md](docs/password-expiration.md).

### Password Change Notifications

Sends an email whenever a user's password changes — covering account settings, forgot-password reset, and admin-initiated changes — with the timestamp, IP address, and (for changes not made by the user themselves) a secure-account link. Configurable independently for customers and admins.

To learn more read [password-change-notifications.md](docs/password-change-notifications.md).

### Two-Factor Authentication

TOTP-based two-factor authentication for shop and admin users (Google Authenticator, Authy, 1Password, …), with QR-code setup, single-use recovery codes, opt-in trusted devices, and per-group enforcement modes (`disabled` / `allowed` / `enforced`). Built on `scheb/2fa-bundle`.

To learn more read [two-factor-authentication.md](docs/two-factor-authentication.md).

### 3rd-party OAuth (Social Login)

Google, Apple and Microsoft sign-in for shop customers and admin users, configured independently per group. Handles account linking with takeover protection, optional domain-gated auto-registration, and an extensible provider registry for adding more providers.

To learn more read [oauth-social-login.md](docs/oauth-social-login.md).

### Magic Link Login

Passwordless email sign-in for shop customers and admin users, independently configurable per group. Single-use, hashed, time-limited tokens with anti-enumeration, timing-attack padding, rate limiting, and 2FA awareness.

To learn more read [magic-link-login.md](docs/magic-link-login.md).

### Passkey Login (WebAuthn / FIDO2)

Passwordless passkey sign-in (Touch ID / Windows Hello / Android lock / hardware keys) for shop customers and admin users, independently configurable per group. Multiple labelled passkeys per user, built on `web-auth/webauthn-lib`, 2FA-aware by default.

To learn more read [passkey-login.md](docs/passkey-login.md).

### Account Lockout & Rate Limiting

Brute-force protection combining persistent per-user account lockout (after N failed sign-ins, with auto- or admin-unlock) and ephemeral request rate limiting (login, password reset, registration, magic link). Independently configurable per group.

To learn more read [account-lockout-rate-limiting.md](docs/account-lockout-rate-limiting.md).

### Session Management & Login Notifications

Active-session listing with manual revocation (single or all-other), plus optional email alerts when a user signs in from a previously unseen device. Independently configurable per group, with pluggable GeoIP location lookup.

To learn more read [session-management-login-notifications.md](docs/session-management-login-notifications.md).

### Centralized Security Settings UI

A single admin page (`/admin/security-settings`) to configure every security feature at runtime — values persist in the database and apply on the next request, no YAML edits or redeploys. Separate Customers / Administrators scopes.

To learn more read [centralized-security-settings-ui.md](docs/centralized-security-settings-ui.md).

### Self-Service Account Deletion (GDPR)

Customer-driven account deletion implementing the GDPR right to erasure, with a configurable grace period, admin-side cancellation, and a cron command that anonymizes name / email / phone / address once the grace period expires.

To learn more read [account-deletion-gdpr.md](docs/account-deletion-gdpr.md).

### Admin IP Whitelist

Restrict admin-panel access to allowed IPs / CIDR ranges, with a team-wide global list plus optional per-admin lists. Network-bound defense-in-depth for fixed-network setups (corporate LAN, VPN, bastion).

To learn more read [admin-ip-whitelist.md](docs/admin-ip-whitelist.md).

### Admin IP Blacklist

Block specific IPs / CIDR ranges from the admin panel with a global deny-list. Always wins over the whitelist, is identity-agnostic (a blocked IP can't even reach the login form), and fails open when empty.

To learn more read [admin-ip-blacklist.md](docs/admin-ip-blacklist.md).

### Admin Customer Management

A Security section on the Sylius customer detail page bundling support actions — force password reset, block / unblock, sign out of all or individual sessions — plus read-only active-sessions and login-history tables.

To learn more read [admin-customer-management.md](docs/admin-customer-management.md).

### Per-User Password Login Control

Disable classic email + password sign-in for individual customers or admins, forcing them onto a stronger method (magic link, passkey, or social login). Per-group global toggle plus a per-user switch, with a lock-out guard.

To learn more read [per-user-password-login-control.md](docs/per-user-password-login-control.md).


## Installation (into an existing Sylius application)

This section is for **consuming** the plugin in your own Sylius project — you register the bundle/plugin and wire the config yourself. If you instead want to **work on the plugin itself**, skip to **Development** below: its bundled test application already has the bundle, plugin and routes registered, so you don't repeat these steps.

> Every feature ships **disabled by default** (see each feature's doc under [`docs/`](docs/)). You enable only what you need in step 3, and the firewall / entity wiring in steps 5–6 is only required for the features you turn on.

1. Require the package:

   ```bash
   composer require 3brs/sylius-enterprise-security-plugin
   ```

2. Register the bundles in `config/bundles.php` (the plugin, its standalone bundle, and the Scheb 2FA bundle it builds on):

   ```php
   return [
       // ...
       Scheb\TwoFactorBundle\SchebTwoFactorBundle::class => ['all' => true],
       ThreeBRS\EnterpriseSecurityBundle\ThreeBRSEnterpriseSecurityBundle::class => ['all' => true],
       ThreeBRS\SyliusEnterpriseSecurityPlugin\ThreeBRSSyliusEnterpriseSecurityPlugin::class => ['all' => true],
   ];
   ```

3. Import the plugin configuration and enable the features you want by creating `config/packages/threebrs_sylius_enterprise_security_plugin.yaml`:

   ```yaml
   imports:
       - { resource: "@ThreeBRSSyliusEnterpriseSecurityPlugin/Resources/config/config.yaml" }

   three_brs_sylius_enterprise_security:
       # Turn on and tune the features you need — each feature's doc under
       # docs/ documents its options and defaults (everything is off by default).
   ```

4. Import the plugin routes by creating `config/routes/three_brs_enterprise_security.yaml` (without this none of the plugin endpoints — passkey, magic link, 2FA setup, OAuth, account deletion, settings UI — are registered):

   ```yaml
   three_brs_enterprise_security:
       resource: "@ThreeBRSSyliusEnterpriseSecurityPlugin/Resources/config/routes.yaml"
   ```

5. Add the relevant traits to your `ShopUser` and `AdminUser` entities. Include **only** the traits for the features you enabled — `PasswordExpiration*` (Password Expiration), `TwoFactorAuth*` (Two-Factor Authentication), `Lockable*` (Account Lockout), `PasswordLoginControl*` (Per-User Password Login Control, admin only). The full set:

   ```php
   // src/Entity/User/ShopUser.php
   use Sylius\Component\Core\Model\ShopUser as BaseShopUser;
   use ThreeBRS\EnterpriseSecurityBundle\Lockout\LockableShopUserInterface;
   use ThreeBRS\EnterpriseSecurityBundle\PasswordExpiration\PasswordExpirationShopUserInterface;
   use ThreeBRS\EnterpriseSecurityBundle\TwoFactor\TwoFactorAuthShopUserInterface;
   use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\LockableShopUserTrait;
   use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\PasswordExpirationShopUserTrait;
   use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\TwoFactorAuthShopUserTrait;

   class ShopUser extends BaseShopUser implements PasswordExpirationShopUserInterface, TwoFactorAuthShopUserInterface, LockableShopUserInterface
   {
       use PasswordExpirationShopUserTrait;
       use TwoFactorAuthShopUserTrait;
       use LockableShopUserTrait;
   }
   ```

   ```php
   // src/Entity/User/AdminUser.php
   use Sylius\Component\Core\Model\AdminUser as BaseAdminUser;
   use ThreeBRS\EnterpriseSecurityBundle\Lockout\LockableAdminUserInterface;
   use ThreeBRS\EnterpriseSecurityBundle\PasswordExpiration\PasswordExpirationAdminUserInterface;
   use ThreeBRS\EnterpriseSecurityBundle\TwoFactor\TwoFactorAuthAdminUserInterface;
   use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\LockableAdminUserTrait;
   use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\PasswordExpirationAdminUserTrait;
   use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\PasswordLoginControlAdminUserInterface;
   use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\PasswordLoginControlAdminUserTrait;
   use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\TwoFactorAuthAdminUserTrait;

   class AdminUser extends BaseAdminUser implements PasswordExpirationAdminUserInterface, TwoFactorAuthAdminUserInterface, LockableAdminUserInterface, PasswordLoginControlAdminUserInterface
   {
       use PasswordExpirationAdminUserTrait;
       use TwoFactorAuthAdminUserTrait;
       use LockableAdminUserTrait;
       use PasswordLoginControlAdminUserTrait;
   }
   ```

   > Magic link, passkey, OAuth, session management, login notifications and account deletion keep their data in their own tables (foreign-keyed to `ShopUser` / `AdminUser`) and need **no** traits.

6. Configure the firewall for the features you enabled, in `config/packages/security.yaml` (and `config/packages/scheb_2fa.yaml` for 2FA). Each feature's doc under `docs/` contains the exact block to copy:
   - **Two-Factor Authentication** — the `scheb_2fa.yaml` config, the shop `success_handler`, and the `two_factor` blocks on the `shop` / `admin` firewalls.
   - **3rd-party OAuth**, **Magic Link Login**, **Passkey Login** — the `PUBLIC_ACCESS` `access_control` entries that expose their login endpoints.

7. Update the database schema to create the plugin tables (`three_brs_*`) and the trait columns added in step 5:

   ```bash
   bin/console doctrine:schema:update --complete --force
   ```

   In production generate and run a migration with your usual workflow instead.

8. Install the bundled assets (e.g. the passkey browser script):

   ```bash
   bin/console assets:install
   ```

## Troubleshooting

### `Cannot create union with both "object" and class type` during cache clear / warmup

If `bin/console cache:clear` (or any route / API metadata warmup) fails with:

```
Cannot create union with both "object" and class type.
```

this is an **upstream api-platform regression, not a plugin bug**. API Platform's property-metadata scanner (Symfony's `PhpStanExtractor` → `TypeInfo`) chokes on generic `@template T of object` PHPDoc present in some of the plugin's transitive dependencies (e.g. `web-auth/webauthn-lib`), trying to build an `object|SomeClass` union that `TypeInfo` rejects. Because API Platform is enabled by default in Sylius 2, you hit it right after installing the plugin.

It affects api-platform `4.3.x` (reproduced on 4.3.5–4.3.7; no fixed release exists at the time of writing). Until an upstream fix ships, work around it by decorating the property-info extractors with a wrapper that swallows the `TypeInfo` exception.

Add the decorator class to your application — use the plugin's [`SafePhpStanExtractor`](tests/Application/src/PropertyInfo/SafePhpStanExtractor.php) as a reference implementation. It implements every property-info extractor interface (on Symfony 7.3+ also `ConstructorArgumentTypeExtractorInterface`) and returns `null` whenever the inner extractor throws `Symfony\Component\TypeInfo\Exception\InvalidArgumentException`. Then register it over both Symfony's and API Platform's extractor services:

```yaml
# config/services.yaml
services:
    App\PropertyInfo\SafePhpStanExtractor:
        arguments: { $inner: '@.inner' }
        decorates: property_info.phpstan_extractor
        decoration_on_invalid: ignore

    app.property_info.safe_php_doc_extractor:
        class: App\PropertyInfo\SafePhpStanExtractor
        arguments: { $inner: '@.inner' }
        decorates: property_info.php_doc_extractor
        decoration_on_invalid: ignore

    app.property_info.safe_reflection_extractor:
        class: App\PropertyInfo\SafePhpStanExtractor
        arguments: { $inner: '@.inner' }
        decorates: property_info.reflection_extractor
        decoration_on_invalid: ignore

    # API Platform registers its own parallel extractor services — decorate those too:
    app.property_info.api_platform_safe_phpstan_extractor:
        class: App\PropertyInfo\SafePhpStanExtractor
        arguments: { $inner: '@.inner' }
        decorates: api_platform.property_info.phpstan_extractor
        decoration_on_invalid: ignore

    app.property_info.api_platform_safe_php_doc_extractor:
        class: App\PropertyInfo\SafePhpStanExtractor
        arguments: { $inner: '@.inner' }
        decorates: api_platform.property_info.php_doc_extractor
        decoration_on_invalid: ignore

    app.property_info.api_platform_safe_reflection_extractor:
        class: App\PropertyInfo\SafePhpStanExtractor
        arguments: { $inner: '@.inner' }
        decorates: api_platform.property_info.reflection_extractor
        decoration_on_invalid: ignore
```

> The decorator is harmless once the upstream bug is fixed (it only catches an exception that no longer fires), but remove it after you upgrade to a fixed api-platform release to keep your container clean.

## Development

This section is **only** for contributing to / developing the plugin — not for installing it into your own app (that's the *Installation* section above). The bundled **test application** under [`tests/Application/`](./tests/Application/) already has the bundle, plugin, routes and feature config registered (it's part of this repo), so you do **not** repeat the Installation steps — `make init` brings the whole stack up ready to go.

### Usage

- Develop the plugin in `/src` (and the framework-agnostic core in [`packages/enterprise-security-bundle/`](./packages/enterprise-security-bundle/))
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
