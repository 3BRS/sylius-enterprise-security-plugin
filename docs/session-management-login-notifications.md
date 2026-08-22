# Session Management & Login Notifications

Active session listing with manual revocation, plus optional email notifications when a user signs in from a previously unseen device. Independently configurable per group (customer / admin).

**Session Management** — every successful sign-in (after a user passes any 2FA or recovery-code challenge) is recorded as a row in `three_brs_customer_session` / `three_brs_admin_user_session` with the User-Agent, IP address, optional country / city, the PHP session ID, plus `created_at`, `last_activity_at`, and `revoked_at` timestamps.

- **Listing UI** — customers see their active sessions at `/{_locale}/account/sessions` (Active sessions item in the account menu); administrators see their own from the **Security** dropdown in the header of their user edit page (Active sessions). Each row shows the parsed browser + OS, IP, location, last-activity time, and a "current" marker on the row matching the request's session ID.
- **Revoke individual session** — a POST form per row marks `revoked_at` on a single record. The *current* session is intentionally non-revocable; sign out instead.
- **Revoke all other sessions** — a top-level POST flips `revoked_at` on every active record except the current one.
- **Activity tracking** — a `kernel.request` listener updates `last_activity_at` on every authenticated request, throttled to **once per 60 seconds** per session to avoid write-amplification on hot pages.
- **Revocation enforcement** — a higher-priority `kernel.request` listener checks the current request's session ID against the store on every authenticated request; if the row is `revoked_at IS NOT NULL`, the listener invalidates the PHP session, clears the security token, and redirects to the corresponding login page (or returns a 401 JSON `{"error":"session_revoked"}` for AJAX / `Accept: application/json` requests). So a revoked session signs the user out on their *next* request, no separate logout call needed. The login routes default to `sylius_shop_login` / `sylius_admin_login`; override `$customerLoginRoute` / `$adminLoginRoute` on the `SessionRevocationListener` service if you renamed them.
- **Bundled MaxMind GeoIP lookup** — plugin ships `MaxMindGeoIpLookup` ready to wire against a local GeoLite2 / GeoIP2 `.mmdb`. Other providers (IP2Location, online APIs, internal services) are pluggable via `GeoIpLookupInterface`. See [Enabling GeoIP location lookups](#enabling-geoip-location-lookups) below.
- **No entity changes required** — sessions and known devices live in their own tables and reference `ShopUser` / `AdminUser` via foreign key; no traits to add.
- **User-Agent parsing** — uses `matomo/device-detector` to extract a human-readable browser name and operating system for both the session list UI and the login-notification email body.

**Login Notifications** — on a successful sign-in, the plugin computes a fingerprint from `sha256(User-Agent + '|' + client IP)`. If that fingerprint isn't already stored in `three_brs_customer_known_device` / `three_brs_admin_user_known_device` for the user, the plugin persists it and sends a `three_brs_login_notification` email containing the time, parsed browser/OS, IP, and (if a GeoIP provider is wired up) country and city. Subsequent logins from the same UA + IP combination are treated as a known device and produce no email.

> **First-time enable on an existing installation:** the known-device table is empty when you first turn `login_notifications` on, so every active user will receive a notification email at their next sign-in (every device is "new" until it lands in the table). Expect a burst of emails right after deploy. If you want to suppress that initial wave, pre-populate `three_brs_*_known_device` rows for each `(user_id, fingerprint)` you consider trusted before flipping the switch.

## Defaults for session management & login notifications

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

The plugin adds an **Active sessions** entry to the shop account menu, shown only when session management is enabled for customers. On the administration side the entry sits in the **Security** dropdown of the administrator's own user edit page, next to two-factor authentication, social accounts and passkeys; it is rendered whatever the setting says, and the page itself answers 404 while session management is off for administrators.

## Enabling GeoIP location lookups

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
       ThreeBRS\EnterpriseSecurityBundle\Session\GeoIp\MaxMindGeoIpLookup:
           arguments:
               $databasePath: '%kernel.project_dir%/var/geoip/GeoLite2-City.mmdb'
   ```
   ```yaml
   # config/packages/threebrs_sylius_enterprise_security_plugin.yaml
   three_brs_sylius_enterprise_security:
       session_management:
           geoip_service: ThreeBRS\EnterpriseSecurityBundle\Session\GeoIp\MaxMindGeoIpLookup
   ```

The plugin's Extension reads `session_management.geoip_service` and replaces the default `NullGeoIpLookup` alias with your service ID — both the customer and admin trackers then call it transparently.

If you'd rather use a different provider (IP2Location, an online API, an internal service…), implement `GeoIpLookupInterface` yourself, register it as a service, and point `geoip_service` at your service ID — same swap mechanism.

> **Localhost / private IPs:** MaxMind GeoLite2 only covers public internet IPs. Lookups for `127.0.0.1`, `::1`, RFC1918 ranges (`10.x`, `172.16–31.x`, `192.168.x`) or Docker bridge networks return `null`, so the session UI will show an IP without country / city when developing locally — that's expected, not a misconfiguration.
>
> **Trusted proxies:** the device fingerprint and the stored IP both use `Request::getClientIp()`. The same trusted-proxy caveat as for rate limiting applies — without `framework.trusted_proxies` configured, all sessions appear to come from the same proxy IP and the new-device check effectively de-duplicates by User-Agent only.
