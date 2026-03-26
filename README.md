# SyliusEnterpriseSecurityPlugin

Advanced security plugin for Sylius e-commerce platform. Provides configurable authentication methods, two-factor authentication, enforced password policies, account protection, and GDPR-compliant account management.

Includes a standalone Symfony bundle (`EnterpriseSecurityBundle`) with reusable security logic that can be used independently of Sylius.

## Features

### Authentication Methods
- **Social login** — Google, Apple, extensible to additional OAuth providers
- **Magic link** — passwordless email-based login
- **Passkeys** — WebAuthn/FIDO2 biometric and hardware key authentication
- **Classic password** — with configurable strength requirements

### Two-Factor Authentication (2FA)
- TOTP support (Google Authenticator, etc.)
- Configurable: disabled / optional / enforced
- **Trusted devices** — remember device for configurable number of days
- **Recovery codes** — backup codes for lost 2FA device

### Password Policies
- Configurable minimum/maximum length and complexity (uppercase, lowercase, numbers, special characters)
- Overrides Sylius default 3-character minimum
- **Password history** — prevent reuse of last N passwords
- **Password expiration** — force password change after X days
- **Change notifications** — email notification on password change (reset or account update)

### Account Protection
- **Account lockout** — lock account after X failed login attempts
- **Rate limiting** — throttle login, password reset, and registration attempts (built on Symfony Rate Limiter)
- **Session management** — view active sessions, revoke other sessions
- **Login notifications** — email alert on login from new device/location

### Admin Panel
- All authentication and security features available for admin users with **independent configuration**
- **IP whitelist** — restrict admin panel access by IP, globally and per admin user
- **Customer management** — force password reset, block/unblock accounts, view login history, remote logout, session management

### GDPR Compliance
- **Self-service account deletion** — customer-initiated account anonymization
- Personal data anonymization with business data retention (orders, payments)
- Configurable confirmation and grace period

### Configuration
- Every feature is fully configurable — enable/disable, set thresholds, time windows
- Independent configuration for customers and admins
- Global configuration (not per channel)

## Architecture

```
sylius-enterprise-security-plugin/
├── packages/
│   └── enterprise-security-bundle/    # Standalone Symfony bundle
│       ├── src/
│       └── composer.json
├── src/                                # Sylius plugin
├── tests/
│   └── Application/                    # Test Sylius application
└── composer.json
```

- **EnterpriseSecurityBundle** — framework-agnostic security logic: interfaces, services, validators, event listeners
- **SyliusEnterpriseSecurityPlugin** — Sylius integration: entity mapping, UI templates, admin panel, Sylius event handling

## Requirements

- PHP 8.2+
- Sylius 2.0+
- Symfony 7.0+

## License

Proprietary. All rights reserved.
