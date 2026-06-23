# Password Login

Controls whether customers and administrators can sign in (and register) with a classic
email + password. It is a single global switch per group (customer / admin) in **Security
settings** — **on by default**. Turn it off for a group and everyone in that group must use a
stronger method instead (a magic link, a passkey, or a connected social account).

- **Per-group toggle** in **Security settings** (customer / admin), **enabled by default**.
- **Login** — when password login is off for a group, the email + password form (and the
  "forgot your password?" link) is hidden on that group's login page; only the enabled
  alternatives (social login, magic link, passkey) remain. A hand-crafted POST is still
  rejected at the authentication layer, so hiding the form is not the only line of defence.
- **Registration** (customers) — when off, the email + password registration form is hidden
  and a direct submission is refused; new customers can then only arrive through OAuth
  auto-registration (a magic link or passkey cannot create a brand-new account).
- **Account UI adapts** — with password login off for customers, the *Change password* /
  *Set a new password* entry disappears from the shop account menu and dashboard.
- **Password management pauses** — while a group's password login is off, the rest of that
  group's password tooling is inert: the password policy / history / expiration /
  change-notification settings render disabled (their stored values are kept), password
  expiration and "force password change" are not enforced, and the admin "force password
  reset" action is hidden. Everything returns exactly as it was once password login is back on.
- **The API is never affected** — token / JSON password authentication keeps working; only
  the web login and registration pages are gated.

## Defaults for password login

```yaml
three_brs_sylius_enterprise_security:
    password_login:
        customer:
            enabled: true
        admin:
            enabled: true
```

Both groups default to **enabled**, so out of the box nothing changes — email + password
login and registration work as usual until you turn a group off in Security settings.

> **Provide another way in first.** Turning a group off does **not** check whether its users
> have an alternative — it disables the password form for everyone in the group
> unconditionally. Before switching it off, make sure at least one other method is enabled for
> that group (social login, magic link, or passkey), or users with no other method will be
> locked out of the web. (They can always be recovered by turning password login back on.)
