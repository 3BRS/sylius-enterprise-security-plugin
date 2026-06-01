# Account Lockout & Rate Limiting

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

> **Limits (enforced in the Security Settings UI):** `max_attempts` 1–20; `auto_unlock_after` 1–86400 seconds; rate-limit `limit` 1–1000.

Add the lockout fields to your `ShopUser` and `AdminUser` entities (same pattern as 2FA / password expiration):

```php
use ThreeBRS\EnterpriseSecurityBundle\Lockout\LockableShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\LockableShopUserTrait;

class ShopUser extends BaseShopUser implements LockableShopUserInterface
{
    use LockableShopUserTrait;
}
```

The trait adds four columns (`failed_login_attempts`, `last_failed_login_at`, `locked_at`, `lockout_until`); run a schema update after adding the trait, e.g. `bin/console doctrine:schema:update --complete --force` or your usual migration workflow.

Plugin adds **Locked customers** and **Locked administrators** entries to the admin Configuration sub-menu automatically — both shown only when lockout is enabled for that group.

> **Trusted proxies:** for password reset, registration, and magic-link rate limits the key is `Request::getClientIp()` (login rate limits use the submitted username so admin unlock can clear them deterministically). If your Sylius runs behind a load balancer or reverse proxy, configure `framework.trusted_proxies` and `framework.trusted_headers` so the real client IP is used — otherwise all non-login requests look like they come from the proxy and the limit triggers immediately.
