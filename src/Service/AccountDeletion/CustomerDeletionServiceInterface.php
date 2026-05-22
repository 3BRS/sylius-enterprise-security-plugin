<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\AccountDeletion;

use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerDeletionRequestInterface;

interface CustomerDeletionServiceInterface
{
    /**
     * Create a deletion request for the customer, disable the linked shop user,
     * send the "deletion requested" email and persist + flush. Throws if the
     * customer already has a pending request.
     */
    public function requestDeletion(CustomerInterface $customer): CustomerDeletionRequestInterface;

    /**
     * Cancel a pending deletion request — re-enables the linked shop user and
     * sets cancelledAt + cancelledByAdmin on the request.
     */
    public function cancelByAdmin(CustomerDeletionRequestInterface $request, AdminUserInterface $admin): void;
}
