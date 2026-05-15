<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\AccountDeletion;

use Sylius\Component\Core\Model\CustomerInterface;

interface CustomerAnonymizerInterface
{
    /**
     * Anonymize the personal data carried on the customer entity, the linked
     * shop user and the customer's address-book entries (name, email, phone,
     * postal address). Business data (orders, payments, order address
     * snapshots) is intentionally retained — see plugin README for rationale.
     */
    public function anonymize(CustomerInterface $customer): void;
}
