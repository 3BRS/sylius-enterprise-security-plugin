# Admin Customer Management

A dedicated **Security** section is added to the standard Sylius customer detail page (`/admin/customers/{id}`). It bundles the day-to-day support actions an operator needs when handling an incident or a customer request without leaving the customer's profile.

The section is rendered via the Sylius twig hook `sylius_admin.customer.show.content.sections`, so it appears automatically once the plugin is installed — no template overrides required. Guest customers (no `ShopUser` row attached) get nothing rendered.

Available actions, each behind a CSRF-protected confirmation prompt:

- **Force password reset** — sets `forcePasswordChange = true` on the shop user. On the customer's next request — whether they are already signed in or sign in afterward — the existing password-expiration listener (shipped with this plugin) redirects them to the change-password page before they can continue browsing. Hidden when [password login](password-login.md) is disabled for customers — there is no password to reset then.
- **Block account** — sets the customer's `enabled` flag to `false` and ends every active session in one step: the disabled account stops being refreshed from the user provider, so any browser still holding a session arrives signed out on its next request, and the tracked session rows are marked revoked as well. Sylius's user checker then rejects further sign-in attempts until you unblock. This is **manual** and **permanent** (until reversed), distinct from the **automatic, time-bounded** account-lockout feature triggered by failed-login attempts: block is for "this customer is misbehaving, lock them out," lockout is for "too many wrong passwords, cool off."
- **Unblock account** — sets `enabled = true`. The customer can sign in again immediately.
- **Sign out from all devices** — revokes every active `CustomerSession` row. Useful after a stolen-device report or a password reset. Distinct from per-session sign-out below.
- **Sign out a single session** — revokes one specific session. The row stays in the login history but is marked ended.

Two read-only tables also live in the section:

- **Active sessions** — every non-revoked `CustomerSession`, with IP, location (country / city if GeoIP is configured), device (user agent), signed-in / last-activity timestamps, and a per-row Sign-out button.
- **Login history** — the last 20 sessions (active and revoked), newest first. Each row shows whether the session is currently active or when it was ended. The list is populated by the session-tracking listener, so it only contains data captured **after** session management was enabled — historical sign-ins from before then are not retroactively visible.

## Prerequisites and interactions

- **Force password reset** depends on the password-expiration listener being registered (it is, by default).
- **Login history** and **session management** depend on `session_management.customer.enabled = true`. If sessions aren't being tracked, the section still renders but the tables stay empty.
- **Block ≠ Lockout.** The plugin keeps both because they answer different questions: block is an admin decision applied indefinitely; lockout is a per-account rate limit that auto-clears.

There is no master switch for this admin tooling — it is always available to administrators.
