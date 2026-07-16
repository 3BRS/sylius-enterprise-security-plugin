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
  The same holds for the **inline sign-in of the checkout address step** (customers): its
  password box disappears and the credentials behind it are refused.
- **Forgotten password closes with it** — with a group's password login off, its *forgotten
  password* pages (request and reset) are closed: a bookmarked link is sent back to the login page
  with a notice instead of handing out a password nobody could sign in with. The *change password*
  page stays reachable — its entry disappears from the account menu, and it hands out nothing on its
  own (it demands the current password, and a password changed there could not be used to sign in).
- **Registration** (customers) — when off, the email + password registration form is hidden
  and a direct submission is refused; new customers can then only arrive through OAuth
  auto-registration or be created by an administrator from the admin panel (a magic link or
  passkey cannot create a brand-new account).
- **Account UI adapts** — with password login off for customers, the *Change password* /
  *Set a new password* entry disappears from the shop account menu and dashboard.
- **Admin panel adapts too** — when a group's password login is off, the password field is
  removed from that group's edit screens in the admin panel, so an administrator can neither
  set nor change a password for anyone in it: for the admin group (gated on the admin toggle)
  the password field is gone from the *Administrator* section of the admin-user edit form, and
  for the customer group (gated on the customer toggle) it is gone from the customer edit form.
  The *force password change* control is hidden in the same place.
- **Accounts are created without a password** — with no password field there is nothing to type,
  so the admin panel stops asking for one: ticking *Enabled* on a guest customer creates their
  shop account, and a new administrator can be created straight away. Such an account is stored
  without a password — exactly like an account created by a social sign-up — and its owner signs in
  with a magic link or a connected social account (a passkey can only be added once they are signed
  in, so it is not a way in for a brand-new account). If password login is turned back on later,
  customers can set a password from *Set a new password* in their account, and administrators
  through *forgot your password*.
- **Password management pauses** — while a group's password login is off, the rest of that
  group's password tooling is inert: the password policy / history / expiration /
  change-notification settings render disabled (their stored values are kept), password
  expiration and "force password change" are not enforced, and the admin "force password
  reset" action is hidden. Everything returns exactly as it was once password login is back on.
- **The API is never affected** — the API firewalls (`/api/v2/…`) behave as if the plugin were not
  installed: their token endpoints keep accepting passwords whatever the switch says. Only the web
  firewalls are gated — including the checkout inline sign-in, which authenticates against the shop
  firewall rather than the API.

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
