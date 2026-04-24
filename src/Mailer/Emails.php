<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer;

interface Emails
{
    public const PASSWORD_CHANGED = 'three_brs_password_changed';

    public const CUSTOMER_MAGIC_LINK = 'three_brs_customer_magic_link';

    public const ADMIN_MAGIC_LINK = 'three_brs_admin_magic_link';
}
