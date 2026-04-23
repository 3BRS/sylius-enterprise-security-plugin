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
- Email contains timestamp, IP address, user agent, and (when the change was not initiated by the user) a secure-account link
- `initiatedByUser` is derived from the current security token: when the authenticated user matches the user whose password changed, the secure-account link is omitted
- Configurable independently for customers and admins (enable/disable)

```yaml
three_brs_sylius_enterprise_security:
    password_change_notification:
        customer:
            enabled: true
        admin:
            enabled: true
```

> **Note (reverse proxy / load balancer):** the IP address included in the email is read from `Request::getClientIp()`, which respects `X-Forwarded-For` only for trusted proxies. If your Sylius runs behind a load balancer or reverse proxy, make sure `framework.trusted_proxies` and `framework.trusted_headers` are configured (e.g. via the `TRUSTED_PROXIES` / `TRUSTED_HEADERS` environment variables) — otherwise the email will log the proxy's address instead of the real client IP. See the [Symfony docs on trusted proxies](https://symfony.com/doc/current/deployment/proxies.html).

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

### Testing

After your changes you must ensure that the tests are still passing.

```bash
make ci
```

## License

MIT License. See [LICENSE](./LICENSE) for details.

## Credits

Developed by [3BRS](https://3brs.com)
