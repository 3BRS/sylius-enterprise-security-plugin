<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity;

use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\AdminUser as BaseAdminUser;
use ThreeBRS\EnterpriseSecurityBundle\Lockout\LockableAdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\LockableAdminUserTrait;
use ThreeBRS\EnterpriseSecurityBundle\PasswordExpiration\PasswordExpirationAdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\PasswordExpirationAdminUserTrait;
use ThreeBRS\EnterpriseSecurityBundle\TwoFactor\TwoFactorAuthAdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\TwoFactorAuthAdminUserTrait;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_admin_user')]
class AdminUser extends BaseAdminUser implements PasswordExpirationAdminUserInterface, TwoFactorAuthAdminUserInterface, LockableAdminUserInterface
{
    use PasswordExpirationAdminUserTrait;
    use TwoFactorAuthAdminUserTrait;
    use LockableAdminUserTrait;
}
