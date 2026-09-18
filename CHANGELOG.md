# Changelog

Notable changes to `3brs/sylius-enterprise-security-plugin`. Follows
[Keep a Changelog](https://keepachangelog.com/) and [SemVer](https://semver.org/).

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

[1.0.0]: https://github.com/3BRS/sylius-enterprise-security-plugin/releases/tag/v1.0.0
