<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Security;

use Symfony\Component\Security\Core\User\UserCheckerInterface;

interface AdminUserLockoutCheckerInterface extends UserCheckerInterface
{
}
