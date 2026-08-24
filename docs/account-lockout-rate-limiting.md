# Account Lockout & Rate Limiting

Brute-force protection covering both account-level lockout (persistent, per user) and request-level rate limiting (ephemeral, keyed per username for login and per IP for other actions). Independently configurable per group (customer / admin).

**Account Lockout** — locks the user account after a configurable number of consecutive failed **password** sign-in attempts.

- Failed-attempts counter and lockout timestamps tracked on the user entity via `LockableShopUserTrait` / `LockableAdminUserTrait` (concurrent failed logins are serialised through a pessimistic row lock so the threshold cannot be bypassed)
- Counter resets on a successful password sign-in
- **Only password sign-in is affected** — a lockout blocks the email + password form only. Passwordless methods (magic link, passkey, OAuth) are never gated by it, so a locked-out user can still sign in that way, and an attacker cannot lock a victim out of their own passkey / magic link by guessing the password wrong. (A locked account is therefore *not* locked out of the site as a whole while any passwordless method is enabled.)
- **Auto-unlock** after `auto_unlock_after` seconds (set to `null` for manual-only)
- **Manual unlock** by admin from `/admin/locked-customers` (under the **Customers** menu) and `/admin/locked-admins` (under **Configuration**). The list shows both the absolute auto-unlock timestamp (*Auto-unlock at*) and the relative countdown (*Time remaining*) for each locked account.
- Both unlock methods can coexist — auto-unlock fires first when `lockoutUntil` is reached, admin can override manually any time
- Locked sign-in attempts get the same generic *"Invalid credentials"* response as a wrong-password attempt — by design, so account state does not leak through error text
- Only storefront and admin-panel sign-ins count toward lockout — failed authentications against the Sylius API (`/api/v2`) are ignored, so an API client can't lock a user out of the web panel
- Fixture (`three_brs_lockout`) to preload accounts with failed-attempt counters, lockout timestamps and an active lockout window, for demo/testing of the unlock screens

**Rate Limiting** — uses the `fixed_window` policy from Symfony Rate Limiter. The plugin builds each limiter at request time through its own `DynamicRateLimiterFactory` (reading the live limits from the Security Settings UI), so there are **no** `framework.rate_limiter.*` services and no manual `framework.yaml` wiring to add.

Throttled endpoints:

| Action | Customer | Admin |
|---|---|---|
| Login | ✓ *(both the login form and the checkout inline sign-in, sharing one counter)* | ✓ |
| Password reset | ✓ | ✓ |
| Register | ✓ | — *(admin has no self-registration)* |
| Magic link | ✓ | ✓ |
| Account deletion request | ✓ | — *(customer-scope feature)* |

The account-deletion request posts the customer's current password, so it presents the same brute-force surface as a password reset and draws on that same counter rather than one of its own — `rate_limit.customer.password_reset` governs both.

When the limit is exceeded a form post is redirected back to the form it came from, with a
`three_brs.rate_limit.too_many_requests` error flash. A request that arrives as JSON — the checkout's inline sign-in posts that
way — is answered with HTTP 429 and `{"error": "three_brs.rate_limit.too_many_requests"}` instead, so the JavaScript that sent
it can read the outcome rather than follow a redirect it never asked for.

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

Plugin adds a **Locked customers** entry under the admin **Customers** menu and a **Locked administrators** entry under **Configuration** automatically — each shown only when lockout is enabled for that group.

> **Trusted proxies:** for password reset, registration, and magic-link rate limits the key is `Request::getClientIp()` (login rate limits use the submitted username so admin unlock can clear them deterministically). If your Sylius runs behind a load balancer or reverse proxy, configure `framework.trusted_proxies` and `framework.trusted_headers` so the real client IP is used — otherwise all non-login requests look like they come from the proxy and the limit triggers immediately.
