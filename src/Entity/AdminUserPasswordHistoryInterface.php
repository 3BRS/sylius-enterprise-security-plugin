<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity;

use Sylius\Component\Core\Model\AdminUserInterface;

interface AdminUserPasswordHistoryInterface
{
    public function getId(): ?int;

    public function getAdminUser(): AdminUserInterface;

    public function setAdminUser(AdminUserInterface $adminUser): void;

    public function getPasswordHash(): string;

    public function setPasswordHash(string $passwordHash): void;

    public function getCreatedAt(): \DateTimeImmutable;
}
