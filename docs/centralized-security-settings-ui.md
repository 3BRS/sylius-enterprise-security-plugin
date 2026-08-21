# Centralized Security Settings UI

A single admin page consolidates configuration for every security feature shipped with the plugin. Administrators can change password policy, lockout thresholds, expiration days, two-factor mode and notification toggles without editing YAML or restarting the container — values are persisted in `three_brs_security_setting` and applied on the next request.

- **Admin route**: `/admin/security-settings` (item *Security settings* in the admin Configuration menu).
- **Scopes**: separate `Customers` and `Administrators` views, stored as `customer` and `admin` rows. The storage model defines a third scope, `global`, for settings that carry no scope dimension; it currently holds nothing, because those settings — the 2FA issuer, the trusted-device window, the passkey relying-party data and the GeoIP service ID — are configured in YAML, for the reasons in the closing paragraphs.
- **Storage**: one row per `(path, scope)` pair, value stored as JSON. The `SettingsProvider` reads the table once per request, in-memory cached, and falls back to YAML defaults when a row is missing — so plugins keep working out of the box and the install command is opt-in.
- **Runtime applied**: `PasswordPolicyValidator`, `PasswordHistoryValidator`, `PasswordExpirationChecker`, `PasswordChangeNotificationListener`, `TwoFactorEnforcementChecker`, lockout policies, `DynamicRateLimiterFactory`, OAuth providers (`isEnabledForCustomer/Admin` reads), `AdminSocialLoginHandler` (whitelist + locale), menu listeners and Twig extensions all read live values via `SettingsProviderInterface` / `PolicyFactoryInterface` / `FeatureToggleInterface`. Compile-time-only values (passkey `rp_id` / `rp_name`, GeoIP service ID, OAuth client secrets / Apple key paths) keep coming from YAML — they are deployment-integration plumbing, not user-facing knobs.
- **Tabs in the UI**: Password login, Password policy, Password history, Password expiration, Password change notification, Two-factor authentication, Magic link, Passkey, Account lockout, Rate limiting, Session management, Login notifications, 3rd-party OAuth — plus, on the **Customers** scope only, OAuth customer auto-registration and Account deletion, and on the **Administrators** scope only, OAuth admin auto-registration, IP whitelist and IP blacklist.
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

OAuth credentials (`client_id`, `client_secret`, Apple `team_id` / `key_id` / `private_key_path`) stay in YAML / `.env.local` — they are deployment-time secrets; putting them in the database would leak them through admin UI display, DB dumps and audit logs. The `3rd-party OAuth` UI tab exposes only the per-provider `enabled` toggle (changing it at runtime takes effect on the next OAuth attempt — providers read the flag through `SettingsProvider`). The `OAuth admin auto-registration` tab (admin scope only) holds the email-domain whitelist and the default locale assigned to admins auto-registered via OAuth — both are policy values, not secrets.

Passkey `rp_id` and `rp_name` similarly stay in YAML — the browser WebAuthn API binds registered credentials to the relying-party ID, so changing it at runtime would invalidate every passkey already registered. The GeoIP service ID is a Symfony service alias resolved at compile time; the implementation behind the alias is a deployment choice, not a runtime knob. Both surface only through YAML.

The `Linked 3rd-party OAuth accounts` shop menu item and the admin Configuration *Linked 3rd-party OAuth accounts* item are now gated through `FeatureToggle` against the same `oauth.google.enabled` / `oauth.apple.enabled` / `oauth.microsoft.enabled` paths used by the providers themselves — toggling a provider off in the Settings UI hides the menu entry on the next request.
