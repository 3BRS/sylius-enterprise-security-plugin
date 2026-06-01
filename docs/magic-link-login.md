# Magic Link Login

- Passwordless sign-in for shop customers and admin users, independently configurable per group
- User submits their email, the plugin generates a single-use token, stores only its SHA-256 hash, and emails a link (`/magic-link/verify/{token}` for shop, `/admin/magic-link/verify/{token}` for admin)
- Following the link signs the user in and marks the token as used — one-time use only
- Separate link (like "Forgotten password?") is rendered on the shop and admin login pages via Sylius twig hooks; no markup changes required in your theme
- Tokens live in dedicated tables (`three_brs_customer_magic_link_token`, `three_brs_admin_user_magic_link_token`) — only hashes are stored, plain tokens exist only in the email
- Anti-enumeration: the request endpoint always responds with the same neutral confirmation whether the email is known, unknown, disabled, or rate-limited — no information about account existence leaks
- Timing-attack mitigation: every code path is padded to a fixed wall-clock deadline (`DeadlineTimingPadding`, default 2 s) so response time does not leak account existence either — known/unknown/rate-limited requests all return at the same time. The 2-second default is chosen to comfortably cover the slowest happy path (DB write + SMTP send) on typical infrastructure; tune it by decorating the `ThreeBRS\EnterpriseSecurityBundle\Timing\DeadlineTimingPadding` service with a different `$targetSeconds` if your SMTP transport is faster or slower than that
- Rate limiting per user: configurable count within a sliding window (defaults to 3 requests / 15 minutes)
- 2FA-aware: if the authenticated user has `scheb/2fa` enabled, the verify controller dispatches `AuthenticationTokenCreatedEvent` on the firewall event dispatcher so scheb wraps the token and redirects to the 2FA challenge — the magic link does **not** bypass the second factor
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

> Magic-link rate limiting (default 3 requests / 15 minutes) is configured separately via the centralized `rate_limit.{customer,admin}.magic_link.{enabled,limit,interval}` block — see [Account Lockout & Rate Limiting](account-lockout-rate-limiting.md).

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
