# Password Policy

- Configurable minimum and maximum password length (overrides Sylius's default 3-character minimum)
- Complexity requirements: uppercase, lowercase, numbers, and special characters — each independently toggleable
- Core validation logic implemented as a reusable Symfony validator in `enterprise-security-bundle` (no Sylius dependency)
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
