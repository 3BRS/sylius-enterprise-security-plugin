<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Model;

enum TwoFactorMode: string
{
    case DISABLED = 'disabled';
    case OPTIONAL = 'optional';
    case ENFORCED = 'enforced';

    public function isDisabled(): bool
    {
        return $this === self::DISABLED;
    }

    public function isEnforced(): bool
    {
        return $this === self::ENFORCED;
    }
}
