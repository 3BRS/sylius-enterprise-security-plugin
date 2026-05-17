<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity;

use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\ShopUser as BaseShopUser;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\LockableShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\LockableShopUserTrait;
use ThreeBRS\EnterpriseSecurityBundle\PasswordExpiration\PasswordExpirationShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\PasswordExpirationShopUserTrait;
use ThreeBRS\EnterpriseSecurityBundle\TwoFactor\TwoFactorAuthShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\TwoFactorAuthShopUserTrait;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_shop_user')]
class ShopUser extends BaseShopUser implements PasswordExpirationShopUserInterface, TwoFactorAuthShopUserInterface, LockableShopUserInterface
{
    use PasswordExpirationShopUserTrait;
    use TwoFactorAuthShopUserTrait;
    use LockableShopUserTrait;
}
