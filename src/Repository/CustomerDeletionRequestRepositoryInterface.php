<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository;

use Sylius\Component\Core\Model\CustomerInterface;
use ThreeBRS\EnterpriseSecurityBundle\AccountDeletion\CustomerDeletionRequestRepositoryInterface as BundleCustomerDeletionRequestRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerDeletionRequestInterface;

interface CustomerDeletionRequestRepositoryInterface extends BundleCustomerDeletionRequestRepositoryInterface
{
    public function findActiveForCustomer(CustomerInterface $customer): ?CustomerDeletionRequestInterface;

    /**
     * @return list<CustomerDeletionRequestInterface>
     */
    public function findDue(\DateTimeImmutable $now): array;

    /**
     * @return list<CustomerDeletionRequestInterface>
     */
    public function findPendingForAdmin(): array;
}
