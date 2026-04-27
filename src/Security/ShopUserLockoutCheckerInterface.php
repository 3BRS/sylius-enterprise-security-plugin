<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Security;

use Symfony\Component\Security\Core\User\UserCheckerInterface;

interface ShopUserLockoutCheckerInterface extends UserCheckerInterface
{
}
