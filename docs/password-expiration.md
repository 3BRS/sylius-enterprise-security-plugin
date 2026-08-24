# Password Expiration

- Forces password change after a configurable number of days
- Supports `force_change` flag to immediately require a password change on next login
- Admin users are redirected to a dedicated change-password page; shop users to the standard change-password flow
- Configurable independently for customers and admins
- Accounts that already existed when the feature was turned on are measured from their **account creation date** until their first password change — so enabling expiration does not force everyone to reset immediately, only accounts already older than the configured window
- Suspended for any group whose **password login** is disabled — with no password to sign in with, expiration and `force_change` are not enforced and the Security Settings controls render disabled until password login is turned back on (see [Password Login](password-login.md))
- Fixture (`three_brs_password_expiration`) to preload users whose password is already stale or flagged for a forced change, for demo/testing

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
