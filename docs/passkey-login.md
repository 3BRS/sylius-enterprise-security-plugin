# Passkey Login (WebAuthn / FIDO2)

- Passwordless sign-in for shop customers and admin users using passkeys (platform authenticators like Touch ID / Windows Hello / Android lock, or hardware security keys such as YubiKey). Independently configurable per group.
- Multiple passkeys per user — labelled (e.g. "MacBook Touch ID", "YubiKey") so the user can identify them. Managed at `/{_locale}/account/passkey` (shop) and `/admin/account/passkey` (admin).
- Per-user credential storage in dedicated tables (`three_brs_customer_passkey_credential`, `three_brs_admin_user_passkey_credential`) — credential ID, public key, sign counter and other metadata serialized as JSON.
- Built on `web-auth/webauthn-lib` — server-side challenge generation and assertion verification follow the standard WebAuthn ceremony.
- Bypasses 2FA: like OAuth and magic link, the verify controller writes the authenticated token directly, so a user with `scheb/2fa` enabled is **not** challenged for the second factor after a passkey sign-in — two-factor only guards plain email + password sign-in
- Enforces the account state: a disabled account — blocked by an administrator, or with a pending [self-service deletion](account-deletion-gdpr.md) — is refused just as on the password form; the sign-in is rejected in place and the button reports it. (An account *locked* by failed password attempts is **not** refused: passwordless sign-in stays available, so nobody can lock a user out of their own passkey by guessing their password wrong.)
- Last-auth-method protection: the existing `LastAuthMethodGuard` is extended to count passkeys, social links and password together; the user cannot remove the last sign-in method on their account.
- Frontend JavaScript (`bundles/threebrssyliusenterprisesecurityplugin/js/passkey.js`) handles `navigator.credentials.create()` / `get()` and the JSON dance with the server. Browsers without the WebAuthn API see a hidden / disabled UI instead of a broken button.
- Sylius twig hooks render a "Sign in with a passkey" button on the shop and admin login pages — no theme changes required.
- Plugin adds a **Passkeys** entry to the shop account menu (`sylius.menu.shop.account`) and to the admin **Configuration** sub-menu (`sylius.menu.admin.main`) automatically — both shown only when the feature is enabled for that group.
- Fixture (`three_brs_passkey`) to preload placeholder credentials for demo/testing of list/remove flows.
- **End-to-end Behat coverage without a real browser** — the `passkey_ceremony` suite runs a PHP-side authenticator emulator (`tests/Behat/Service/Passkey/FakeAuthenticator`) that generates real ES256 keypairs in PHP, signs assertions and serializes WebAuthn structures (CBOR + COSE) exactly as a real authenticator would. Server-side `web-auth/webauthn-lib` validates the signed payloads end-to-end, so the full register / login ceremony is covered without Selenium, Panther or a browser. Run with `APP_ENV=test ./bin-docker/php vendor/bin/behat --suite=shop_passkey_ceremony` (and the admin variant). Note: this layer does **not** exercise the JavaScript glue in `passkey.js`; that is left for a separate JS unit-test suite if/when added.

## Defaults for passkey

```yaml
three_brs_sylius_enterprise_security:
    passkey:
        rp_id: ~
        rp_name: ~
        customer:
            enabled: false
        admin:
            enabled: false
```

## Required configuration to enable passkeys

`rp_id` and `rp_name` are `null` by default and must be set before passkeys can be enabled — if the feature is turned on for either group while they are still null, the container fails to build with an explicit error (`rp_id and rp_name must be configured when passkey is enabled`). Minimum config to turn the feature on for shop customers:

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
