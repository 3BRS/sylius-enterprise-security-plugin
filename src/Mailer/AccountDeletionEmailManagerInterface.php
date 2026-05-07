<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer;

use Sylius\Component\Core\Model\CustomerInterface;

interface AccountDeletionEmailManagerInterface
{
    public function sendDeletionRequested(CustomerInterface $customer, \DateTimeImmutable $scheduledFor): void;

    /**
     * Must be called BEFORE anonymization runs — at this point Customer.email
     * still points at the user's real address.
     */
    public function sendDeletionCompleted(CustomerInterface $customer): void;
}
