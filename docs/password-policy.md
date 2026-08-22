# Password Policy

- Configurable minimum and maximum password length, applied alongside Sylius's own four-character
  minimum on `plainPassword`, so the stricter of the two decides. Where both would complain about a
  short password, only the policy message is shown.
- Complexity requirements: uppercase, lowercase, numbers, and special characters — each independently toggleable
- The `PasswordPolicy` constraint, the policy model and the violation filter live in
  `enterprise-security-bundle` and carry no Sylius dependency; the constraint validator that reads the
  policy and checks the password is the plugin's `PasswordPolicyValidator`, which the constraint
  resolves to through the `three_brs.validator.password_policy` alias
- Sylius plugin layer applies the policy to `ShopUser` (customer) and `AdminUser` entities with separate configuration for each

## Defaults for password policy

```yaml
three_brs_sylius_enterprise_security:
    password_policy:
        customer:
            min_length: 8
            max_length: ~
            require_uppercase: false
            require_lowercase: false
            require_numbers: false
            require_special_characters: false

        admin:
            min_length: 12
            max_length: ~
            require_uppercase: true
            require_lowercase: true
            require_numbers: true
            require_special_characters: true
```

> **Limits (enforced in the Security Settings UI):** `min_length` 1–64, `max_length` 1–128 — and `max_length` must be ≥ `min_length`.
