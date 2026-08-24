# Two-Factor Authentication

- TOTP-based 2FA for shop and admin users (compatible with Google Authenticator, Authy, 1Password, etc.)
- QR code + manual secret setup, from the account page in the storefront and from the **Security** dropdown in the header of an
  administrator's own user edit page in the panel
- Recovery codes — single-use backup codes generated at setup, regenerable from the manage view (invalidates all previous codes)
- Trusted device — opt-in cookie (scheb JWT) to skip 2FA on a known device; revocable per user by bumping the user's `trustedTokenVersion`
- Enforcement modes per user type: `disabled`, `allowed`, `enforced`. In `enforced` mode a user without 2FA is redirected to the setup page until they enable it
- Firewall integration via `scheb/2fa-bundle` with separate `/2fa` (shop) and `/admin/2fa` (admin) challenge endpoints
- 2FA guards plain email + password sign-in only — passwordless methods (OAuth, passkey, magic link) authenticate directly and bypass the second factor by design
- The Sylius API (`/api/v2`) is never gated by 2FA — API clients are machines that can't present a second factor, so the API authenticates exactly as in standard Sylius even when the mode is `enforced`
- Fixture (`three_brs_two_factor`) to preload 2FA-enabled users and recovery codes for demo/testing
- Plugin exposes container parameters (`three_brs.two_factor.issuer`, `three_brs.two_factor.trusted_device_enabled`, `three_brs.two_factor.trusted_device_lifetime`) that can be referenced directly from your `scheb_2fa.yaml`

```yaml
three_brs_sylius_enterprise_security:
    two_factor_authentication:
        issuer: 'Sylius'
        customer:
            mode: 'disabled'  # disabled | allowed | enforced
        admin:
            mode: 'disabled'  # disabled | allowed | enforced
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

> **Limits:** recovery codes `count` 1–10 (Security Settings UI); trusted-device `days` 1–365 (set via YAML config).

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
        template: '@ThreeBRSSyliusEnterpriseSecurityPlugin/TwoFactor/challenge.html.twig'
```

`template` is what puts the plugin's challenge page in front of the user. Both challenge
routes hand off to scheb's own form controller, which renders whatever this names; left
unset it falls back to `@SchebTwoFactor/Authentication/form.html.twig`, a bare form outside
the Sylius layout with no link to the recovery-code challenge — so the recovery codes
issued during setup could not be used to sign in.

On the **shop firewall**, replace Sylius' default `form_login.success_handler` (`sylius.authentication.success_handler`) with the plugin's 2FA-aware wrapper. The default Sylius handler returns a `JsonResponse` on XHR and redirects straight to the target path without checking for a `TwoFactorTokenInterface`, which produces a broken UX during 2FA challenges:

```yaml
# config/packages/security.yaml
security:
    firewalls:
        shop:
            form_login:
                success_handler: ThreeBRS\EnterpriseSecurityBundle\TwoFactor\TwoFactorAwareAuthenticationSuccessHandler.shop
            two_factor:
                auth_form_path: three_brs_shop_two_factor_challenge
                check_path: three_brs_shop_two_factor_check
                prepare_on_login: true
                prepare_on_access_denied: true
        admin:
            two_factor:
                auth_form_path: three_brs_admin_two_factor_challenge
                check_path: three_brs_admin_two_factor_check
                prepare_on_login: true
                prepare_on_access_denied: true

    access_control:
        # A token holding a pending second factor carries no roles, so the challenge
        # pages have to sit above whatever rule guards the rest of the panel.
        - { path: ^/2fa, role: IS_AUTHENTICATED_2FA_IN_PROGRESS }
        - { path: "%sylius.security.admin_regex%/2fa", role: IS_AUTHENTICATED_2FA_IN_PROGRESS }
        # ... your remaining rules, including the admin catch-all, below this
```

The paths are given as route names rather than URLs: Sylius takes the administration
path from `SYLIUS_ADMIN_ROUTING_PATH_NAME`, so `/admin/2fa` holds only for an
installation that left it alone, while the route name follows it.

The admin firewall does not need a custom `success_handler` — Sylius does not override it there, so the default Symfony handler is used and scheb's `TwoFactorAccessListener` transparently redirects authenticated-but-not-yet-verified admins to `/admin/2fa` on the next request.
