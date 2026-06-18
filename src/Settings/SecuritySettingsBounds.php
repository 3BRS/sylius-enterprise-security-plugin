<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings;

/**
 * Single source of truth for the upper (and non-trivial lower) bounds enforced
 * on security settings. Consumed by the Security Settings form types (HTML
 * `min`/`max` attributes + `Range` constraints) and mirrored by the YAML
 * `Configuration` tree, so a limit lives in exactly one place.
 */
interface SecuritySettingsBounds
{
    public const PASSWORD_POLICY_MIN_LENGTH_MAX = 64;

    public const PASSWORD_POLICY_MAX_LENGTH_MAX = 128;

    public const PASSWORD_HISTORY_COUNT_MAX = 24;

    public const PASSWORD_EXPIRATION_DAYS_MAX = 730;

    public const TWO_FACTOR_RECOVERY_CODES_COUNT_MAX = 10;

    public const TWO_FACTOR_TRUSTED_DEVICE_DAYS_MAX = 365;

    public const MAGIC_LINK_EXPIRATION_SECONDS_MIN = 60;

    public const MAGIC_LINK_EXPIRATION_SECONDS_MAX = 3600;

    public const ACCOUNT_LOCKOUT_MAX_ATTEMPTS_MAX = 20;

    public const ACCOUNT_LOCKOUT_AUTO_UNLOCK_AFTER_MAX = 86400;

    public const ACCOUNT_DELETION_GRACE_PERIOD_DAYS_MAX = 90;

    public const RATE_LIMIT_LIMIT_MAX = 1000;

    public const OAUTH_ALLOWED_EMAIL_DOMAINS_MAX = 100;

    public const OAUTH_EMAIL_DOMAIN_LENGTH_MAX = 253;

    public const STRING_SETTING_LENGTH_MAX = 255;
}
