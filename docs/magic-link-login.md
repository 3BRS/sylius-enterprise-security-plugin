# Magic Link Login

- Passwordless sign-in for shop customers and admin users, independently configurable per group
- User submits their email, the plugin generates a single-use token, stores only its SHA-256 hash, and emails a link (`/magic-link/verify/{token}` for shop, `/admin/magic-link/verify/{token}` for admin)
- Following the link signs the user in and marks the token as used — one-time use only
- Separate link (like "Forgotten password?") is rendered on the shop and admin login pages via Sylius twig hooks; no markup changes required in your theme
- Tokens live in dedicated tables (`three_brs_customer_magic_link_token`, `three_brs_admin_user_magic_link_token`) — only hashes are stored, plain tokens exist only in the email
- Anti-enumeration: the request endpoint answers with the same neutral confirmation whether the email is known, unknown or belongs to a disabled account — no information about account existence leaks. A request the rate limiter turns away is the exception: it is answered above the controller with the `three_brs.rate_limit.too_many_requests` error flash, and since that limiter is keyed on the client address rather than the submitted email, what it reveals is the caller's own throttled state
- Timing-attack mitigation: the request handler pads the work it does to a fixed wall-clock deadline (`DeadlineTimingPadding`, default 2 s), so a known and an unknown address are answered after the same elapsed time and response time does not leak account existence either. A throttled request returns sooner, because the rate limiter answers it before the handler runs; as above, that reflects the caller's address and not any account. The 2-second default is chosen to comfortably cover the slowest happy path (DB write + SMTP send) on typical infrastructure; tune it by decorating the `ThreeBRS\EnterpriseSecurityBundle\Timing\DeadlineTimingPadding` service with a different `$targetSeconds` if your SMTP transport is faster or slower than that
- Rate limiting: an optional per-IP request cap (`fixed_window` policy), **off by default** — when enabled, 3 requests / 15 minutes
- Bypasses 2FA: like OAuth and passkey, the verify controller writes the authenticated token directly, so a user with `scheb/2fa` enabled is **not** challenged for the second factor after following the link — two-factor only guards plain email + password sign-in
- Enforces the account state: a disabled account — blocked by an administrator, or with a pending [self-service deletion](account-deletion-gdpr.md) — is refused, just as it is on the password form. The magic link bounces back to the *sign in with a link* page with a notice **without being consumed**, so it still works once the account is enabled again; [OAuth](oauth-social-login.md) bounces to the login page (and the social link it was about to create is not created); [passkey](passkey-login.md) sign-in is rejected in place and the button reports it. (An account locked by failed password attempts is *not* refused: passwordless sign-in stays available, so nobody can lock a user out of their own passkey by guessing their password wrong.)
- Fixture (`three_brs_magic_link`) to preload tokens for demo/testing

```yaml
three_brs_sylius_enterprise_security:
    magic_link:
        customer:
            enabled: false
            expiration_seconds: 300      # 5 minutes
        admin:
            enabled: false
            expiration_seconds: 300
```

> **Limits (enforced in the Security Settings UI):** `expiration_seconds` 60–3600 (1 minute – 1 hour).

> Magic-link rate limiting is **off by default** (3 requests / 15 minutes when enabled) and is configured separately via the centralized `rate_limit.{customer,admin}.magic_link.{enabled,limit,interval}` block — see [Account Lockout & Rate Limiting](account-lockout-rate-limiting.md).

Expose the request and verify endpoints as public in your firewall access control (the verify controller authenticates internally):

```yaml
# config/packages/security.yaml
security:
    access_control:
        - { path: ^/magic-link$, role: PUBLIC_ACCESS }
        - { path: ^/magic-link/verify/, role: PUBLIC_ACCESS }
        - { path: "%sylius.security.admin_regex%/magic-link$", role: PUBLIC_ACCESS }
        - { path: "%sylius.security.admin_regex%/magic-link/verify/", role: PUBLIC_ACCESS }
```
