# Password Change Notifications

- Sends an email notification whenever a user's password is changed
- Covers all flows: account settings change, forgot-password reset, admin-forced change, and admin editing another user's password
- Detection is Doctrine-based — the listener catches password updates at flush time regardless of which flow triggered them
- Email contains timestamp, IP address (when available), and a secure-account link when the change was not initiated by the user
- `initiatedByUser` is derived from the current security token: when the authenticated user matches the user whose password changed, the secure-account link is omitted
- Configurable independently for customers and admins (enable/disable)

```yaml
three_brs_sylius_enterprise_security:
    password_change_notification:
        customer:
            enabled: false
        admin:
            enabled: false
```

> **Note (reverse proxy / load balancer):** the IP address included in the email is read from `Request::getClientIp()`, which respects `X-Forwarded-For` only for trusted proxies. If your Sylius runs behind a load balancer or reverse proxy, make sure `framework.trusted_proxies` and `framework.trusted_headers` are configured (e.g. via the `TRUSTED_PROXIES` / `TRUSTED_HEADERS` environment variables) — otherwise the email will log the proxy's address instead of the real client IP. See the [Symfony docs on trusted proxies](https://symfony.com/doc/current/deployment/proxies.html).
