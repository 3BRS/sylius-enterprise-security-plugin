<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service;

use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserPasswordHistoryRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerPasswordHistoryRepositoryInterface;

class PasswordHistoryChecker implements PasswordHistoryCheckerInterface
{
    public function __construct(
        private CustomerPasswordHistoryRepositoryInterface $customerRepository,
        private AdminUserPasswordHistoryRepositoryInterface $adminRepository,
    ) {
    }

    public function wasPasswordUsedByShopUser(ShopUserInterface $user, string $plainPassword, int $count): bool
    {
        foreach ($this->customerRepository->findRecentByShopUser($user, $count) as $entry) {
            if (password_verify($plainPassword, $entry->getPasswordHash())) {
                return true;
            }
        }

        return false;
    }

    public function wasPasswordUsedByAdminUser(AdminUserInterface $user, string $plainPassword, int $count): bool
    {
        foreach ($this->adminRepository->findRecentByAdminUser($user, $count) as $entry) {
            if (password_verify($plainPassword, $entry->getPasswordHash())) {
                return true;
            }
        }

        return false;
    }
}
