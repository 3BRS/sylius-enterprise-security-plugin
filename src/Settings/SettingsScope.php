<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings;

enum SettingsScope: string
{
    case CUSTOMER = 'customer';
    case ADMIN = 'admin';
    case GLOBAL = 'global';
}
