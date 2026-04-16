<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserRecoveryCodeRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerRecoveryCodeRepositoryInterface;

class RecoveryCodeVerifier implements RecoveryCodeVerifierInterface
{
    public function __construct(
        private CustomerRecoveryCodeRepositoryInterface $customerRepository,
        private AdminUserRecoveryCodeRepositoryInterface $adminRepository,
        private RecoveryCodeGeneratorInterface $generator,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    public function verifyAndConsumeForShopUser(ShopUserInterface $user, string $plainCode): bool
    {
        $record = $this->customerRepository->findUnusedByShopUserAndHash($user, $this->generator->hash($plainCode));
        if ($record === null) {
            return false;
        }

        $record->setUsedAt($this->clock->now());
        $this->entityManager->flush();

        return true;
    }

    public function verifyAndConsumeForAdminUser(AdminUserInterface $user, string $plainCode): bool
    {
        $record = $this->adminRepository->findUnusedByAdminUserAndHash($user, $this->generator->hash($plainCode));
        if ($record === null) {
            return false;
        }

        $record->setUsedAt($this->clock->now());
        $this->entityManager->flush();

        return true;
    }
}
