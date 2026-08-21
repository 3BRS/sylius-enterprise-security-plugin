# Admin IP Whitelist

Restrict admin panel access to a configured set of IP addresses or CIDR ranges. The feature has two layers that solve different problems:

- **Global list** — team-wide allow. Use this when everyone shares the same network: corporate LAN, VPN exit gateway, office static IP, cloud bastion CIDR. Configured under Security settings → Administrators → "Admin IP whitelist".
- **Per-admin list** — personal extras that don't belong in the team-wide list. Managed on `/admin/ip-whitelist/admins`. Pick an administrator and toggle their personal allow-list on/off with its own CIDR set. An admin's CIDRs stay private to that admin — they are not exposed in the global view, and they grant access only when **that specific admin** signs in. Useful when admin A occasionally signs in from a home IP that has no business being in the team-wide list (where it would also unlock admin B and the rest).

Access is granted when **either** the request IP matches the global list **or** the authenticated admin's own (enabled) list matches. A failed check returns HTTP 403 with a plain-text body — there is no redirect or login form fallback.

**Global list is mandatory when the feature is enabled.** The Security settings form rejects saving `enabled = true` with an empty global list — at least one global CIDR must be configured. This guard prevents the most common self-lockout scenario (someone flips the master switch on without filling anything in). At runtime, the post-auth check then fans out into per-admin entries on top of the global list. The listener runs in two passes for this reason. The first sits above Symfony's firewall and knows nothing about identity: it lets a request through when the IP matches the global list **or any** enabled per-admin entry, so an admin can reach the login form, and it covers `/admin/login-check` and `/admin/2fa_check`, which the firewall answers before a listener below it would ever run. The second sits below the firewall, where the administrator is known, and narrows the check to **that specific admin's** entry (otherwise the session is rejected on the next request). This means an attacker who lands on admin A's home IP still can't sign in as admin B — the per-admin check binds the IP to the identity.

If you really want pure per-admin enforcement with no team-wide allow, add `0.0.0.0/0` and `::/0` to the global list as an explicit acknowledgement that the team-wide layer is intentionally wide open and only per-admin entries restrict access.

```yaml
three_brs_sylius_enterprise_security:
    ip_whitelist:
        enabled: false
```

## Defaults for IP whitelist

- `ip_whitelist.global_cidrs` defaults to `[]` (no IPs configured). This is admin-scope only and is edited through the Security settings UI.

The Configuration node only exposes the master switch. The actual allow-lists live in the database (DB-backed settings + a per-admin entity, `three_brs_admin_user_ip_whitelist`) so that operators can change them at runtime without redeploying.

> **Operator note.** If you enable the feature with an empty global list **and no enabled per-admin entries** that cover your IP, all administrators will be locked out of the panel. Either configure at least one matching CIDR (global or per-admin) before enabling, or disable the feature again via SQL (`UPDATE three_brs_security_setting SET value = 'false' WHERE scope = 'admin' AND path = 'ip_whitelist.enabled'`) to recover. CIDR validation accepts both IPv4 (e.g. `10.0.0.0/8`, `192.168.1.1`) and IPv6 (e.g. `2001:db8::/32`, `::1`).

## When IP whitelist is the right tool

This feature is network-bound — it only helps when the admins reach the panel from a predictable IP range. Use it when administrators sit on a corporate LAN with a known public IP, connect through a VPN that exits to a fixed CIDR, or when the admin host is itself a cloud VM with a static address.

It's **not** the right control when admins log in from rotating home IPs (PPPoE / DHCP), mobile data behind CG-NAT, or arbitrary travel networks — they will lock themselves out the moment their ISP rotates the lease. In those cases, leave the master switch off and rely on the device-/possession-bound controls already shipped in this plugin: 2FA + passkeys + account lockout + rate limiting. Those follow the user instead of the network, so a changing IP doesn't break them. IP whitelist is defense-in-depth on top of fixed-network setups, not a replacement for those factors.
