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
     * The pending predicate belongs in the query rather than in the caller: cancelling
     * one that is already cancelled or completed throws, and the controller's contract
     * with the bundle is "found or not".
     */
    public function findPendingById(int $id): ?CustomerDeletionRequestInterface;

    /**
     * @return list<CustomerDeletionRequestInterface>
     */
    public function findDue(\DateTimeImmutable $now): array;

    /**
     * @return list<CustomerDeletionRequestInterface>
     */
    public function findPendingForAdmin(): array;
}
