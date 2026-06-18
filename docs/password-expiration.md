# Password Expiration

- Forces password change after a configurable number of days
- Supports `force_change` flag to immediately require a password change on next login
- Admin users are redirected to a dedicated change-password page; shop users to the standard change-password flow
- Configurable independently for customers and admins
- Accounts that already existed when the feature was turned on are measured from their **account creation date** until their first password change — so enabling expiration does not force everyone to reset immediately, only accounts already older than the configured window

## Defaults for password expiration

```yaml
three_brs_sylius_enterprise_security:
    password_expiration:
        customer:
            enabled: false
            days: 365
        admin:
            enabled: false
            days: 365
```

> **Limits (enforced in the Security Settings UI):** `days` 1–730.
