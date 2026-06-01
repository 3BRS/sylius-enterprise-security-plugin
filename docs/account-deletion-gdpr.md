# Self-Service Account Deletion (GDPR)

Customer-driven account deletion implementing the GDPR right to erasure, with a configurable grace period and admin-side cancellation.

- **Customer-facing flow** — when enabled, the *Delete account* item appears in the shop account menu (`/{_locale}/account/delete`). The customer enters their current password (re-authentication, no email round-trip) and explicitly acknowledges the consequences. On submit:
  1. A `three_brs_customer_deletion_request` row is created with `requested_at = now`, `scheduled_for = now + grace_period_days`.
  2. The linked `ShopUser` is set to `enabled = false` immediately — login stops working at once.
  3. The customer's session is invalidated.
  4. A `three_brs_account_deletion_requested` email is sent confirming the schedule and instructing the customer to contact a store administrator if they change their mind.
- **Cancellation** — customer-initiated cancellation is intentionally NOT exposed: once submitted, the request can only be cancelled by an administrator from `/admin/account-deletions` (sub-menu under Configuration). Cancelling re-enables the `ShopUser` and stamps the request with `cancelled_at` + `cancelled_by_admin_id` for audit.
- **Grace expiry** — a console command processes due requests:
  ```
  bin/console three-brs:account-deletion:process-due
  ```
  Hook this into a cron job (every hour is fine):
  ```
  0 * * * * php /path/to/app/bin/console three-brs:account-deletion:process-due
  ```
  For each due request the command sends a `three_brs_account_deletion_completed` email **before** anonymizing (Customer.email is still live at that point), then anonymizes, then stamps `completed_at`.

**What gets anonymized** (literal interpretation of GDPR personal data: name, email, phone, address):
- `Customer.firstName` → `Deleted`, `Customer.lastName` → `User`
- `Customer.email` / `emailCanonical` → `deleted-{id}@anonymized.invalid`
- `Customer.phoneNumber` → `null`
- Every entry in the customer's address book (`sylius_address.customer_id = ...`): `firstName`, `lastName`, `street`, `city`, `postcode`, `phoneNumber` are scrubbed

**What is intentionally retained** — order rows, payment rows, order address snapshots (`sylius_order.billing_address_id` / `shipping_address_id`), 2FA secrets, recovery codes, passkey credentials, magic-link tokens, social-account links, sessions, password history. Two reasons: the spec scope is *name / email / phone / address*, and order/payment data has legitimate accounting / tax-retention requirements that take precedence over erasure. After anonymization the order browser still resolves orders to a customer row, but the row reads "Deleted User". Plugin users who need stricter erasure can layer on a project-level cleanup pass.

```yaml
three_brs_sylius_enterprise_security:
    account_deletion:
        customer:
            enabled: false
            grace_period_days: 30
```

> **Limits (enforced in the Security Settings UI):** `grace_period_days` 1–90.

The feature is intentionally customer-scope only — admin self-deletion is not exposed (admin lifecycle is operations-team responsibility, not GDPR self-service).

> **Cron is required.** Without `three-brs:account-deletion:process-due` running periodically, deletion requests reach `scheduled_for` but never anonymize — the customer stays disabled but their personal data lingers in the DB indefinitely.
