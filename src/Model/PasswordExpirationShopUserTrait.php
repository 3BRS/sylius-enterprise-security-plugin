<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Model;

/**
 * The storefront name for PasswordExpirationUserTrait, which holds the columns and the
 * behaviour for both sides. Sylius keeps ShopUser and AdminUser as separate
 * entities, but a trait carries no type identity, so there is one copy to
 * change and two names to reach it by.
 */
trait PasswordExpirationShopUserTrait
{
    use PasswordExpirationUserTrait;
}
