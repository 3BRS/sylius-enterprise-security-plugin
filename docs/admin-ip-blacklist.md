# Admin IP Blacklist

Inverse of the whitelist — instead of saying "only these IPs can reach the panel", say "these specific IPs cannot reach the panel". A single **global** deny-list applies to every request under `/admin`. Useful when you don't want to lock everyone to a fixed network but need to block a specific bad actor: a former colleague's home IP, an exit node from an abuse report, or a host hammering the login form.

The global list is configured under Security settings → Administrators → "Admin IP blacklist".

**Blacklist always wins over the whitelist.** A blacklisted IP cannot sign in, even if the whitelist would otherwise allow it. The blacklist request listener runs at priority 5, before the whitelist listener at priority 4, so a blacklist hit short-circuits the whitelist check entirely. This ordering means you can keep a permissive whitelist (or none) for the team while still being able to block individual abusive IPs.

The check is identity-agnostic: any request whose client IP matches the global list is denied with HTTP 403 (plain-text body), whether or not anyone is signed in — so a known-bad IP cannot even reach the login form.

```yaml
three_brs_sylius_enterprise_security:
    ip_blacklist:
        enabled: false
```

## Defaults for IP blacklist

- `ip_blacklist.enabled` defaults to `false`.
- `ip_blacklist.global_cidrs` defaults to `[]` (no IPs configured). This is admin-scope only and is edited through the Security settings UI.

The Configuration node only exposes the master switch. The actual deny-list lives in the database (DB-backed settings) so that operators can change it at runtime without redeploying.

> **Fail-open by default.** Unlike the whitelist, enabling the blacklist with an empty global list does **not** lock anyone out — an empty deny list blocks nothing. This makes the blacklist safe to toggle on as a precaution and populate later.

> **Operator note.** If you accidentally blacklist your own IP and lock yourself out, recover via SQL: `DELETE FROM three_brs_security_setting WHERE scope = 'admin' AND path IN ('ip_blacklist.enabled', 'ip_blacklist.global_cidrs')` (resets the global list and feature flag). CIDR validation accepts both IPv4 and IPv6.

If you're behind a reverse proxy or load balancer, configure Symfony's `framework.trusted_proxies` so that `Request::getClientIp()` returns the real client IP rather than the proxy address — otherwise the listener compares the proxy IP against your CIDR list and you'll either let everyone in or lock everyone out.
