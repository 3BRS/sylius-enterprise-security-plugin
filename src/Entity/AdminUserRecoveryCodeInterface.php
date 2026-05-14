<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity;

use Sylius\Component\Core\Model\AdminUserInterface;

interface AdminUserRecoveryCodeInterface
{
    public function getId(): ?int;

    public function getAdminUser(): AdminUserInterface;

    public function setAdminUser(AdminUserInterface $adminUser): void;

    public function getCodeHash(): string;

    public function setCodeHash(string $codeHash): void;

    public function getCreatedAt(): \DateTimeImmutable;

    public function getUsedAt(): ?\DateTimeImmutable;

    public function setUsedAt(?\DateTimeImmutable $usedAt): void;

    public function isUsed(): bool;
}
