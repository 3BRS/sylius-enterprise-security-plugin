<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity;

use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use ThreeBRS\EnterpriseSecurityBundle\AccountDeletion\CustomerDeletionRequestRecordInterface;

interface CustomerDeletionRequestInterface extends CustomerDeletionRequestRecordInterface
{
    public function getId(): ?int;

    public function getCustomer(): CustomerInterface;

    public function setCustomer(CustomerInterface $customer): void;

    public function getCancelledByAdmin(): ?AdminUserInterface;

    public function setCancelledByAdmin(?AdminUserInterface $admin): void;
}
