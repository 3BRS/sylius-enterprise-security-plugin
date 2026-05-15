<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer;

interface Emails
{
    public const PASSWORD_CHANGED = 'three_brs_password_changed';

    public const MAGIC_LINK = 'three_brs_magic_link';

    public const LOGIN_NOTIFICATION = 'three_brs_login_notification';
}
