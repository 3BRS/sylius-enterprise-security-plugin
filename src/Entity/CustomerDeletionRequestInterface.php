<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity;

use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\CustomerInterface;

interface CustomerDeletionRequestInterface
{
    public function getId(): ?int;

    public function getCustomer(): CustomerInterface;

    public function setCustomer(CustomerInterface $customer): void;

    public function getRequestedAt(): \DateTimeImmutable;

    public function getScheduledFor(): \DateTimeImmutable;

    public function setScheduledFor(\DateTimeImmutable $scheduledFor): void;

    public function getCancelledAt(): ?\DateTimeImmutable;

    public function setCancelledAt(?\DateTimeImmutable $cancelledAt): void;

    public function getCancelledByAdmin(): ?AdminUserInterface;

    public function setCancelledByAdmin(?AdminUserInterface $admin): void;

    public function getCompletedAt(): ?\DateTimeImmutable;

    public function setCompletedAt(?\DateTimeImmutable $completedAt): void;

    public function isPending(): bool;
}
