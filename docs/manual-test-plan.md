# Manual Test Plan

An exhaustive, feature-by-feature plan for verifying this plugin by hand in a real
Sylius application. Every configuration key, default, route, table and email name
below is read from the source, not from prose:
`src/DependencyInjection/Configuration.php`, `config/routes.yaml` (66 routes),
`src/EventListener/FeatureToggleListener.php`, `src/Mailer/Emails.php`, `src/Entity/*`.

Automated coverage (PHPUnit, Behat) lives in `tests/` and `features/`. This document
covers what those cannot: browser ceremonies (WebAuthn, TOTP), real mail delivery,
cross-feature interaction, and the operational traps that make a working feature
look broken.

---

## 0. How to read this

- **§2 is the trap list.** Read it before enabling anything. Most "it doesn't work"
  reports against this plugin resolve to something in there.
- **§4** is the complete feature table with defaults.
- **§5** holds scenarios `T01`–`T73`, each independently runnable.
- **§6** covers combinations, where genuine bugs tend to hide.
- **§9** is how to get back in after locking yourself out. You will lock yourself out.

Record results against each `→ expect:`. Anything else is a finding — write down the
exact steps, not "broken".

---

## 1. What the application under test must provide

This plan is application-agnostic. Before starting, note down your app's values for:

| Requirement | Why | Your value |
|---|---|---|
| Shop URL | most customer scenarios | |
| Admin URL (`%sylius_admin.path_name%`) | admin scenarios | |
| **A mail catcher** (MailHog, Mailpit, `MAILER_DSN=smtp://…`) | six of the features are only observable as email | |
| Direct database access (`psql` / `mysql`) | every recovery procedure in §9 | |
| A shell in the app container | `bin/console` | |
| Whether OAuth providers are real or faked in dev | §2.13 | |

**Two URL conventions used throughout.** `/admin` stands for whatever
`%sylius_admin.path_name%` resolves to. Shop account paths are written `/account/…` for
brevity, but every one of them is declared with a **mandatory `{_locale}` segment** —
the real URL is `/en_US/account/sessions`, not `/account/sessions`, and requesting the
latter does not route at all. The only shop routes without a locale prefix are
`/magic-link*`, `/oauth/*` and `/passkey/{register,login}/*`. When a scenario says a page
should be reachable and you get a 404, check the locale segment before concluding
anything.

After any YAML change: `bin/console cache:clear`. Changes made in the admin
Security Settings page apply on the next request and need no cache clear.

---

## 2. Preconditions and traps — read before testing

### 2.1 Two layers of "enabled", and both must agree

Nearly every feature has **two switches**, combined with a logical AND:

1. **YAML** (`three_brs_sylius_enterprise_security:` in the app's config) — whether the
   feature is configured at all. Read when the container is built.
2. **Security Settings** (`/admin/security-settings`) — the runtime switch, stored in
   the `three_brs_security_setting` table.

`ScopedFeatureChecker::isEnabled()` returns true only when **both** say yes.
Enabling a feature in the admin will **not** rescue one disabled in YAML, and vice
versa. When something "won't turn on", check both layers.

There are two scopes: `customer` (shop) and `admin`. Almost everything is configured
separately per scope — a feature enabled for customers is **not** enabled for admins.

### 2.2 A disabled feature returns 404, not a hidden link

`FeatureToggleListener` guards **31 routes**. A disabled feature means the direct URL
returns **HTTP 404** — not a redirect, not a 403. This is deliberate: before it existed,
an administrator could switch a feature off, watch the menu entry disappear, and still
reach every endpoint by typing its URL. When verifying "is it really off", test the URL,
not just the missing menu entry. Full matrix in **§7**.

### 2.3 Passkeys (WebAuthn) — the host matters more than anything else

- WebAuthn requires a **secure context**: either `https://` or **`http://localhost`**.
  An app served over plain HTTP on any other hostname cannot run the ceremony at all.
- `passkey.rp_id` must equal the browser's host (or be a registrable suffix of it).
  Mismatch produces *"The relying party ID is not a registrable domain suffix of, nor
  equal to the current domain"*. That is **not a plugin bug** — align `rp_id` with the
  host, and give the host TLS if it is not `localhost`.
- Reaching the same app through a different name (`127.0.0.1` instead of `localhost`,
  a LAN IP, a tunnel) breaks it for the same reason.
- **No platform authenticator** (headless Linux, WSL2, a VM without Touch ID / Windows
  Hello)? Use Chrome's virtual authenticator: `F12` → DevTools `⋮` → **More tools →
  WebAuthn** → *Enable virtual authenticator environment* → **Add** (protocol `ctap2`,
  transport `internal`, tick *Supports resident keys* and *Supports user verification*).
  Registration and login then complete with no hardware, and the panel lists the stored
  credential. The panel must stay open for the duration.
- A registered passkey is bound to authenticator + `rp_id`. Disabling the virtual
  authenticator loses it and leaves an orphan row in
  `three_brs_{customer,admin_user}_passkey_credential`.

### 2.4 TOTP (2FA) — clocks, cookies, and single-use codes

- A TOTP code is valid within ±1 time window. If the **container clock** drifts from the
  phone's (common on WSL2 after suspend), valid codes are rejected. Check with `date`
  inside the container against the host.
- No phone? The QR encodes an `otpauth://` URI containing `secret=`; compute codes from
  it directly (`oathtool --totp -b <SECRET>`).
- **Recovery codes are single-use.** A code failing the second time is correct behaviour,
  not a bug. Regenerate at `/admin/two-factor/recovery-codes/regenerate` (or the shop
  equivalent).
- "Trusted device" is a **cookie**. To test that the challenge reappears, use a private
  window or clear the cookie — otherwise you sail past 2FA and it looks like a hole.
- The challenge template is shared between shop and admin and distinguishes them by
  **route name** (`three_brs_admin_two_factor_challenge`), not by URL prefix. If the app
  changes its admin path, confirm the admin challenge still renders as admin.

### 2.5 Email — everything lands in the catcher

Six features are only observable as mail (§8). If nothing arrives, verify the catcher is
running and that the DSN actually loaded: `bin/console debug:config framework mailer`.

Magic links **expire** (default 300 s) and are **single-use**. Both are requirements, not
defects — but the refusal is only observable **while signed out**: the verify controller
short-circuits for an already-authenticated visitor and simply redirects them onward
without re-consuming the token. Clicking the link a second time in the same browser
therefore looks like it "worked". To see the single-use guarantee, sign out (or use a
fresh browser profile) before the second click.

Magic link and passkey are **passwordless and deliberately bypass 2FA** (the second factor
guards password login only). Do not report this as a hole; it is a documented decision.

### 2.6 Rate limiting — state lives in cache, not the database

Limits are held by `symfony/rate-limiter` through the cache. Once exhausted you stay
blocked for the whole interval (`15 minutes` for login by default). Either wait, or clear
the pool:

```bash
bin/console cache:pool:list          # find the rate limiter pool
bin/console cache:pool:clear <pool>
```

The counter is per identity/IP, so two browser windows share it.

### 2.7 Account lockout — you will lock out your own account

The admin scope defaults to `max_attempts: 3`. Three typos and you are out until
`auto_unlock_after` elapses (default `null` — **never**) or another administrator unlocks
you at `/admin/locked-admins`. **Keep a spare administrator account** (§3). SQL recovery
in §9.2.

### 2.8 IP whitelist / blacklist — the fastest way to lock yourself out of admin

- **The blacklist always wins over the whitelist**, and it is identity-agnostic — it does
  not care who you are. Blacklisting your own address closes the admin panel for
  superadmins too.
- **The two lists fail in opposite directions, and this is the single easiest thing to get
  wrong.** An empty *blacklist* fails **open** — it blocks nobody. An enabled *whitelist*
  with an empty global list (and no matching per-administrator entry) fails **closed** and
  locks out every administrator. The settings form refuses to save that combination, so the
  only way to reach it is by writing the setting straight into the database — which is
  exactly what an over-eager test does. A whitelist that simply does not contain your
  address ejects you immediately.
- **What is your address?** Behind Docker or a reverse proxy it is almost never
  `127.0.0.1` — typically a bridge address (`172.x.x.x`). **Never guess.** Determine it
  before enabling anything: read `REMOTE_ADDR` from the Symfony profiler, or the access
  log. If the app sits behind a proxy and the address is wrong, that is Symfony's
  `trusted_proxies`, not this plugin.
- Add your address to the whitelist **first**, enable it **second**. Recovery in §9.1.

### 2.9 Account deletion — the plugin ships the command, not the schedule

The plugin provides `three-brs:account-deletion:process-due` and **does not schedule it**.
The application must run it (cron, Kubernetes CronJob, scheduler bundle). Unless the app
has wired that up, a grace period never elapses on its own — run the command by hand:

```bash
bin/console three-brs:account-deletion:process-due
```

To see a completed deletion without waiting out the grace period, move the due date in
the database (§9.4). **Anonymisation is irreversible** — never test it on an account you
still need.

### 2.10 Runtime values versus YAML

`magic_link.expiration_seconds` and `account_deletion.grace_period_days` are read at
runtime from stored settings. **The YAML value is only the seed default** — whatever sits
in `three_brs_security_setting` wins, so editing YAML may have no visible effect on a
running app. Change them in the admin, or delete the row.

Both are additionally **clamped to the bounds in `SecuritySettingsBounds`**, so an
out-of-range value written straight to the database will not have the effect you expect.

### 2.11 Password policy has no off switch

`password_policy` is the only feature with **no `enabled` node** — it applies from
installation. Defaults: customers minimum 8 characters; admins minimum 12 plus uppercase,
lowercase, number and special character. If seeding users with simple passwords suddenly
fails, this is why.

### 2.12 Password login can be switched off — and then you cannot get in

`password_login` is the only feature enabled by default (`defaultTrue`). Turning it off
for `admin` without a working magic link, passkey or OAuth path **closes the admin panel**.
Establish the alternative route first.

### 2.13 OAuth in development

Real Google/Apple/Microsoft credentials are rarely available in a dev environment. An
application may substitute a fake provider service tagged `three_brs.oauth_provider` for
the bundle's own provider class; check the app's `config/services_dev.yaml`. A well-built
fake still honours the runtime `enabled` flag from `SettingsProvider`, so a disabled
provider shows no button — meaning the logic under test is real even though the network
call is not.

Fakes are usually `dev`-only. Running the same scenarios under `APP_ENV=prod` without real
credentials will fail — that is the environment, not the plugin.

---

## 3. Test identities — create these first

Establish these before touching any switch:

| Purpose | Notes |
|---|---|
| **Rescue administrator** | full admin rights, **never used in any lockout / IP / 2FA scenario** |
| Victim administrator | for lockout, 2FA, forced password change |
| Customer A | main shop scenarios |
| Customer B | isolation checks — sessions, passkeys, password history must never leak across accounts |
| Customer C | single-use, for the destructive deletion scenarios (T60–T63) |

```bash
bin/console sylius:admin-user:create
```

---

## 4. Feature overview

Defaults below are from `Configuration.php`, not from any application's configuration.
Scope `C` = customer/shop, `A` = admin.

| # | Feature | YAML node | Default | Scope | Runtime switch | Main trap |
|---|---|---|---|---|---|---|
| 1 | Password Policy | `password_policy` | **no on/off**; C: min 8; A: min 12 + 4 rules | C, A | yes | cannot be disabled (§2.11) |
| 2 | Password History | `password_history` | `enabled: false`; C: 5, A: 10 | C, A | yes | history only fills from the moment it is enabled |
| 3 | Password Expiration | `password_expiration` | `enabled: false`, 365 days | C, A | yes | existing accounts measured from `createdAt` |
| 4 | Password Change Notifications | `password_change_notification` | `enabled: false` | C, A | yes | observable only in the mail catcher |
| 5 | Two-Factor Authentication | `two_factor_authentication` | `mode: disabled`; issuer `Sylius`; recovery codes `true`/8; trusted device `true`/60 days | C, A | yes | clock drift + trusted-device cookie (§2.4) |
| 6 | OAuth Social Login | `oauth` | all providers `false`; `tenant: common`; admin `default_locale: en_US`; `auto_register_allowed_email_domains: []` | C, A | yes | dev fakes (§2.13) |
| 7 | Magic Link | `magic_link` | `enabled: false`, 300 s | C, A | yes | single-use + expiry; bypasses 2FA |
| 8 | Passkey (WebAuthn) | `passkey` | `enabled: false`; `rp_id`/`rp_name` `null` | C, A | yes | secure context + `rp_id` (§2.3) |
| 9 | Account Lockout | `account_lockout` | `enabled: false`; C: 5 attempts, A: 3; `auto_unlock_after: null` | C, A | yes | locks you out (§2.7) |
| 10 | Rate Limiting | `rate_limit` | all `false`; login 5/15 min, reset 3/1 h, register 5/1 h (C only), magic link 3/15 min | C, A | yes | state in cache (§2.6) |
| 11 | Session Management | `session_management` | `enabled: false`; `geoip_service: null` | C, A | yes | no GeoIP service means no location shown |
| 12 | Login Notifications | `login_notifications` | `enabled: false` | C, A | yes | mail only from an *unknown* device |
| 13 | Security Settings UI | — | always available at `/admin/security-settings` | — | — | one page per scope: 15 sections (C) / 16 (A) |
| 14 | Account Deletion (GDPR) | `account_deletion` | `enabled: false`, 30 days | **C only** | yes | no schedule shipped (§2.9); irreversible |
| 15 | Admin IP Whitelist | `ip_whitelist` | `enabled: false` | A only | yes | closes the admin panel (§2.8) |
| 16 | Admin IP Blacklist | `ip_blacklist` | `enabled: false` | A only | yes | wins over the whitelist |
| 17 | Password Login | `password_login` | **`enabled: true`** | C, A | yes | disabling locks you out (§2.12) |
| 18 | Admin Customer Management | — | no configuration, always active | A only | — | Security section on the customer detail page |

**Scope asymmetries worth remembering:** `account_deletion` has only `customer`.
`ip_whitelist` and `ip_blacklist` have only a global `enabled` (no per-scope branch) and
apply to the admin panel. `rate_limit.customer` has a `register` limiter that
`rate_limit.admin` does not.

---

## 5. Scenarios

Format: **precondition → configuration → steps → expected result.** Reset settings between
sections so scenarios do not contaminate each other (§9.5).

### 5.1 Password Policy (T01–T05)

| ID | Test | Steps | → expect |
|---|---|---|---|
| T01 | Customer, short password | register with `Ab1!` (4 chars) | rejected, minimum-length message |
| T02 | Customer, boundary | exactly 8 characters | accepted |
| T03 | Admin, missing special char | `Abcdefgh1234` (12 chars, no symbol) | rejected |
| T04 | Admin, satisfies all rules | `Abcdefgh123!` | accepted |
| T05 | `max_length` | set `max_length: 20`, try 21 characters | rejected |

Verify all five entry points, since a validator is easy to wire to only some of them:
shop registration, account password change, forgotten-password reset, admin user creation,
and an administrator changing a customer's password.

### 5.2 Password History (T06–T09)

Enable `password_history.customer` with `count: 3`.

| ID | Test | → expect |
|---|---|---|
| T06 | Change to B, then back to A | rejected — A is in history |
| T07 | Cycle A→B→C→D, then try A | accepted — A has aged out of the last 3 |
| T08 | Customer A and customer B use the same password | allowed — history is per user |
| T09 | Customer history vs. admin history | separate, never mixed |

Trap: history only starts filling when the feature is enabled. Immediately after enabling
it is empty and the first change always succeeds.

### 5.3 Password Expiration (T10–T13)

| ID | Test | Setup | → expect |
|---|---|---|---|
| T10 | Expired password | `days: 1`, move `password_changed_at` back 2 days in the DB | login redirects to a forced change |
| T11 | Not expired | `days: 365` | normal login |
| T12 | Account that never changed its password | any seeded account | measured from `createdAt`, so enabling the feature must not expire everyone at once |
| T13 | Admin forced change | `/admin/force-password-change` | no other admin page reachable until changed |

### 5.4 Password Change Notifications (T14–T17)

Enable for both scopes; check the mail catcher after each.

| ID | Path to the password change | → expect |
|---|---|---|
| T14 | Customer changes it in their account | `three_brs_password_changed` with timestamp and IP, and **no** secure-account link — the link and the warning block render only for changes the user did not initiate |
| T15 | Forgotten-password reset | same email |
| T16 | Administrator changes a customer's password | email goes **to the customer**, and this one **does** carry the warning block and secure-account link (not user-initiated) |
| T17 | Administrator changes their own | email to the administrator |

Trap: the IP in the email will be the proxy/bridge address unless the app trusts proxies
(§2.8).

### 5.5 Two-Factor Authentication (T18–T27)

| ID | Test | → expect |
|---|---|---|
| T18 | `mode: disabled` | the account-menu entry disappears, **but the setup route itself is not gated** — it still responds. `FeatureToggleListener` covers no 2FA route. Confirm the menu entry is gone; a reachable URL here is expected, not a finding |
| T19 | `mode: allowed`, user opts out | login with password only |
| T20 | `mode: allowed`, user opts in | challenge after the password |
| T21 | `mode: enforced` | a user without 2FA is forced into setup after login |
| T22 | Setup QR | QR plus `otpauth://` secret; issuer matches configuration |
| T23 | Wrong code | rejected, not logged in |
| T24 | Recovery code | logs in; **the same code fails the second time** |
| T25 | Regenerate recovery codes | previously issued codes stop working |
| T26 | Trusted device enabled | second login from the same browser skips the challenge |
| T27 | Same, in a private window | challenge reappears |

Run the whole set twice — once for the shop (`/account/two-factor/setup`) and once for the
admin (`/admin/two-factor/setup`). They are separate scopes with separate tables.

### 5.6 OAuth Social Login (T28–T34)

| ID | Test | → expect |
|---|---|---|
| T28 | Provider disabled | no button; `/oauth/{provider}/start` unreachable |
| T29 | Provider enabled, new identity | signed in as the provider's identity |
| T30 | Linking to an existing account | `three_brs_oauth_link_code` email; the code is single-use and time-limited |
| T31 | Wrong or expired code | link refused |
| T32 | Unlinking | `/account/social-accounts` → unlink, association gone |
| T33 | Admin with `auto_register_allowed_email_domains: []` | an unknown identity at the admin login is **refused** (self-registration off) |
| T34 | Domain added to the list | registration succeeds |

T33/T34 is the pair worth care: an empty list is the safe default and is easy to get wrong.

### 5.7 Magic Link (T35–T39)

| ID | Test | → expect |
|---|---|---|
| T35 | Request a link | `three_brs_magic_link` in the catcher |
| T36 | Click it | signed in without a password |
| T37 | Click the same link again | refused (single-use) |
| T38 | After expiry | set 60 s, wait, click → refused |
| T39 | Unknown email address | **identical response and comparable timing** to a known address (anti-enumeration + timing padding) |

T39 is the security substance of the feature — measure it, do not assume. A different
message, or a clearly different response time, is a finding.

### 5.8 Passkey (T40–T44)

Set up §2.3 first.

| ID | Test | → expect |
|---|---|---|
| T40 | Register a passkey | appears in the passkey list with its label |
| T41 | Sign in with it | signed in without a password; **2FA is not requested** (by design) |
| T42 | Multiple passkeys | both listed, both work |
| T43 | Delete one | gone from the list; signing in with it fails |
| T44 | Another user's passkey | customer A's key must never sign in customer B |

Repeat for the admin scope at `/admin/account/passkey`.

### 5.9 Account Lockout (T45–T48)

**Use the victim account, never the rescue administrator.**

| ID | Test | → expect |
|---|---|---|
| T45 | N failed attempts | after `max_attempts` the account is locked **even with the correct password** |
| T46 | Administrator unlock | `/admin/locked-customers` or `/admin/locked-admins` → unlock, login works |
| T47 | `auto_unlock_after` | set 60 s; unlocks itself after a minute |
| T48 | `auto_unlock_after: null` | stays locked until an administrator intervenes |

### 5.10 Rate Limiting (T49–T51)

| ID | Test | → expect |
|---|---|---|
| T49 | Login limiter | after `limit` attempts, refused **regardless of password correctness** |
| T50 | Password-reset limiter | refused after 3 requests in an hour |
| T51 | Magic-link limiter | refused after 3 requests in 15 minutes |

The distinction that matters: rate limiting is **ephemeral (cache) and per IP/identity**;
lockout is **persistent (database) and per account**. Verify they are not being confused
for one another — compare T45 with T49.

### 5.11 Session Management (T52–T56)

| ID | Test | → expect |
|---|---|---|
| T52 | Session list | `/account/sessions` shows the current browser |
| T53 | Two sessions | sign in from a second browser → two entries |
| T54 | Revoke one | the other browser is signed out; the current one continues |
| T55 | Revoke all others | only the current session remains |
| T56 | Administrator revokes a customer's session | the customer is signed out |

Traps: with `geoip_service: null` the location column stays empty — expected. The stored
user agent is **truncated to 1024 characters** by the entity.

### 5.12 Login Notifications (T57–T59)

| ID | Test | → expect |
|---|---|---|
| T57 | First sign-in from a browser | `three_brs_login_notification` email |
| T58 | Second sign-in from the same browser | **no email** (device now known) |
| T59 | Genuinely different browser (different User-Agent) or different IP | email again. A **private window of the same browser is not enough** — the known-device key is `sha256(userAgent\|ipAddress)` with no cookie involved, so the fingerprint already matches and no mail is sent |

### 5.13 Account Deletion — GDPR (T60–T63)

**Destructive. Use customer C.**

| ID | Test | → expect |
|---|---|---|
| T60 | Request deletion | `three_brs_account_deletion_requested` email; entry in `/admin/account-deletions` |
| T61 | Administrator cancels | request leaves the pending list; the account survives |
| T62 | Grace period elapses | move the due date (§9.4), run the command → name, email, phone and address anonymised; `three_brs_account_deletion_completed` email |
| T63 | Cancel an already-completed request | **404, not 500** — covers two open tabs and the race with the cron |

### 5.14 IP Whitelist / Blacklist (T64–T67)

**Read §2.8 and have §9.1 open in another window first.**

| ID | Test | → expect |
|---|---|---|
| T64 | Whitelist containing your address | admin works normally |
| T65 | Whitelist without your address | admin refused |
| T66 | Per-administrator list | the per-admin list is an **additional allowance, never a narrowing**: the global list is consulted first and a global match admits the administrator regardless of their own list. To exercise the per-admin path, the address must be **absent** from the global list and **present** in the administrator's own |
| T67 | Address on both lists | **refused** — the blacklist wins |

### 5.15 Admin Customer Management (T68–T70)

The Security section on the Sylius customer detail page.

| ID | Test | → expect |
|---|---|---|
| T68 | Force password reset | the customer must change their password at next login |
| T69 | Block / unblock | a blocked customer cannot sign in; unblocking restores access |
| T70 | Session and login-history tables | show real data |

### 5.16 Password Login disabled (T71–T73)

**Establish a working magic link or passkey before disabling this.**

| ID | Test | → expect |
|---|---|---|
| T71 | Disabled for customers | login and registration forms hidden in the shop, **including the inline sign-in during checkout**; forgotten-password pages closed |
| T72 | Administrator creates a customer | account created without a password |
| T73 | Password expiration while disabled | paused — nobody is forced to rotate a password they cannot sign in with |

---

## 6. Combinations and interactions

This is where real bugs hide: features pass individually and fail in pairs.

| # | Combination | What to verify |
|---|---|---|
| K1 | 2FA `enforced` + magic link | the magic link **bypasses** 2FA — confirm that holds under `enforced` and that the deployment accepts it |
| K2 | 2FA `enforced` + passkey | same |
| K3 | Password login OFF + 2FA `enforced` | nothing left for the second factor to guard — must not produce a redirect loop |
| K4 | Lockout + rate limiting together | which triggers first? They must not mask each other (T45 vs. T49) |
| K5 | Lockout + magic link | **lockout does not block a magic link** — the verify controllers use Sylius' `EnabledUserChecker`, which only rejects disabled accounts, and lockout guards password login. Verify the documented behaviour holds, and decide whether the deployment accepts it |
| K6 | Lockout + OAuth | same as K5 — the OAuth callbacks bind the same enabled-only checker and contain no lockout check. Verify, then decide |
| K7 | Whitelist and blacklist on the same address | blacklist wins (T67) |
| K8 | IP whitelist + magic link into admin | the link must not bypass the IP restriction |
| K9 | Password history + expiration | a forced change must still refuse a password from history |
| K10 | Password policy + history | the rejection must say **which** rule failed, not blur the two |
| K11 | Session revocation + trusted device | revoking a session must not silently clear device trust, nor leave the session alive |
| K12 | Account deletion + active sessions | an anonymised account must be signed out everywhere |
| K13 | Password change notification + admin-initiated reset | the email goes to the **customer**, not the administrator |
| K14 | Login notification + trusted device | two different notions of "known device" — they must not be conflated |
| K15 | Feature disabled at runtime + direct URL | 404 (§7) |
| K16 | Customer A vs. customer B | no feature may leak data between accounts (T08, T44, T54) |
| K17 | Customer scope ON + admin scope OFF | for every dual-scope feature, confirm disabling one scope leaves the other untouched |

Work through K17 systematically across all dual-scope features — it is the most common
place for configuration to bleed between scopes.

---

## 7. Feature-toggle → 404 matrix

Disable the feature at `/admin/security-settings`, then request the URL directly. Each must
return 404.

| Disabled feature | Scope | Routes that must 404 |
|---|---|---|
| `passkey` | customer | `/account/passkey`, `/account/passkey/{id}/delete`, `/passkey/register/options`, `/passkey/register/verify`, `/passkey/login/options`, `/passkey/login/verify` |
| `passkey` | admin | `/admin/account/passkey`, `/admin/account/passkey/{id}/delete`, `/admin/passkey/register/options`, `/admin/passkey/register/verify`, `/admin/passkey/login/options`, `/admin/passkey/login/verify` |
| `magic_link` | customer | `/magic-link`, `/magic-link/verify/{token}` |
| `magic_link` | admin | `/admin/magic-link`, `/admin/magic-link/verify/{token}` |
| `session_management` | customer | `/account/sessions`, `/account/sessions/revoke-others`, `/account/sessions/{id}/revoke`, `/admin/customers/{id}/revoke-all-sessions`, `/admin/customers/{id}/sessions/{sessionId}/revoke` |
| `session_management` | admin | `/admin/account/sessions`, `/admin/account/sessions/revoke-others`, `/admin/account/sessions/{id}/revoke` |
| `account_lockout` | customer | `/admin/locked-customers`, `/admin/locked-customers/{id}/unlock` |
| `account_lockout` | admin | `/admin/locked-admins`, `/admin/locked-admins/{id}/unlock` |
| `account_deletion` | customer | `/account/delete`, `/admin/account-deletions`, `/admin/account-deletions/{id}/cancel` |

31 routes in total. Two entries look surprising and are worth confirming deliberately:

- `/admin/customers/{id}/revoke-*-session*` hangs off the **customer** scope, not admin —
  correct, since these are the customer's sessions.
- `/admin/account-deletions*` also hangs off the **customer** scope, because account
  deletion exists for customers only.

---

## 8. Emails

Six kinds, from `src/Mailer/Emails.php`:

| Code | Sent when | Scenario |
|---|---|---|
| `three_brs_password_changed` | any password change | T14–T17 |
| `three_brs_magic_link` | a magic link is requested | T35 |
| `three_brs_login_notification` | sign-in from an unknown device | T57 |
| `three_brs_account_deletion_requested` | deletion requested | T60 |
| `three_brs_account_deletion_completed` | anonymisation finished | T62 |
| `three_brs_oauth_link_code` | linking OAuth to an existing account | T30 |

For each, check the sender, the subject, that **no raw translation key** (`three_brs.…`)
is visible — an untranslated key is a finding — and that links point at the right host.

---

## 9. Recovery

All tables are prefixed `three_brs_`. Adjust the SQL dialect to the app's database.

### 9.1 Locked out of admin by the IP whitelist/blacklist
```sql
DELETE FROM three_brs_security_setting WHERE path LIKE 'ip_whitelist%' OR path LIKE 'ip_blacklist%';
```
Falls back to the YAML defaults (`enabled: false` for both), reopening the admin panel.
Per-administrator lists: `DELETE FROM three_brs_admin_user_ip_whitelist;`

### 9.2 Locked out by account lockout
```sql
DELETE FROM three_brs_security_setting WHERE path LIKE 'account_lockout%';
```
Disables lockout globally. A narrower unlock is available at `/admin/locked-admins` — if
another account can still reach it.

### 9.3 Cannot pass 2FA (authenticator lost)
```sql
DELETE FROM three_brs_admin_user_recovery_code WHERE admin_user_id = <ID>;
DELETE FROM three_brs_security_setting WHERE path LIKE 'two_factor_authentication%';
```
Also clear the TOTP secret column contributed by `TwoFactorAuthAdminUserTrait` — confirm
its name against the live schema rather than assuming.

### 9.4 See a completed deletion without waiting out the grace period
```sql
UPDATE three_brs_customer_deletion_request
SET scheduled_for = NOW() - INTERVAL '1 day'
WHERE id = <ID>;
```
Confirm the column name against the live schema, then run
`bin/console three-brs:account-deletion:process-due`.

### 9.5 Zero state — discard every runtime setting
```sql
DELETE FROM three_brs_security_setting;
```
Everything falls back to YAML defaults. The fastest way out of a broken state, and a good
starting point before each block of scenarios.

### 9.6 Full data reset

Rebuild the schema and reload fixtures using the application's own commands.

---

## 10. Cleanup and reporting

- Reset settings after each section — §9.5 is the quickest route.
- Accounts anonymised in T62 **do not come back**; reload fixtures afterwards.
- Report a finding as: plugin version/commit, scope, the exact state of **both**
  configuration layers (§2.1), steps, expected versus actual, and whether it reproduces
  from a zero state (§9.5).
- Before calling something a plugin bug, re-read §2. Most surprises live there.

## Plugin tables (reference)

`three_brs_security_setting`, and per scope:
`three_brs_{customer,admin_user}_password_history`,
`three_brs_{customer,admin_user}_recovery_code`,
`three_brs_{customer,admin_user}_magic_link_token`,
`three_brs_{customer,admin_user}_passkey_credential`,
`three_brs_{customer,admin_user}_session`,
`three_brs_{customer,admin_user}_known_device`,
`three_brs_{customer,admin_user}_social_account_link`,
plus `three_brs_customer_deletion_request` and `three_brs_admin_user_ip_whitelist`.
