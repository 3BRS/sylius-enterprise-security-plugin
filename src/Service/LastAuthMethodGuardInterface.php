<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service;

use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ShopUserInterface;

interface LastAuthMethodGuardInterface
{
    public function canUnlinkSocialForShopUser(ShopUserInterface $user, string $provider): bool;

    public function canUnlinkSocialForAdminUser(AdminUserInterface $user, string $provider): bool;

    public function canRemovePasskeyForShopUser(ShopUserInterface $user): bool;

    public function canRemovePasskeyForAdminUser(AdminUserInterface $user): bool;

    /**
     * Whether password login may be disabled for this shop user without locking
     * them out — true only if they keep at least one alternative sign-in method
     * (an OAuth link, a passkey, or globally enabled magic link).
     */
    public function canDisablePasswordLoginForShopUser(ShopUserInterface $user): bool;

    public function canDisablePasswordLoginForAdminUser(AdminUserInterface $user): bool;
}
