# Per-User Password Login Control

Lets you disable classic email + password sign-in for individual customers or administrators, forcing them onto a stronger method (magic link, passkey, or a connected social account). Useful for high-privilege admins who should only sign in with a passkey, or accounts you want to migrate off passwords.

- **Global feature toggle** per group (customer / admin) in **Security settings** — off by default.
- **Per-user switch** on the customer detail page and the admin user edit page. When password login is disabled for a user, a form sign-in attempt is rejected with an explicit message pointing them to the alternatives.
- **Lock-out guard** — the switch refuses to disable password login for a user who has no other way in (no connected social account, no passkey, and magic link not enabled for their group), so an account can never be stripped of every sign-in method.
- OAuth, passkey and magic-link sign-ins are never affected — only the password form is gated.
- **Account UI adapts** — while password login is disabled for a customer, the *Change password* / *Set a new password* entry is hidden from the shop account menu and dashboard (there is no password for them to manage).

## Defaults for password login control

```yaml
three_brs_sylius_enterprise_security:
    password_login_control:
        customer:
            enabled: false
        admin:
            enabled: false
```

When the feature is disabled for a group, per-user switches have no effect — every user in that group can sign in with their password as usual.

> **Operator note.** The lock-out guard runs only at the moment you disable password login for a user — it is **not** re-checked afterwards. So if a user has password login disabled and relies on a globally-toggled method (magic link, passkey, or a specific OAuth provider), and you later turn that method off for their group, they can be left with no way to sign in. To recover, re-enable that method, or re-enable password login for the affected user.
