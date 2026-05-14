<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity;

use Sylius\Component\Core\Model\AdminUserInterface;

interface AdminUserMagicLinkTokenInterface
{
    public function getId(): ?int;

    public function getAdminUser(): AdminUserInterface;

    public function setAdminUser(AdminUserInterface $adminUser): void;

    public function getTokenHash(): string;

    public function setTokenHash(string $tokenHash): void;

    public function getExpiresAt(): \DateTimeImmutable;

    public function setExpiresAt(\DateTimeImmutable $expiresAt): void;

    public function getUsedAt(): ?\DateTimeImmutable;

    public function setUsedAt(?\DateTimeImmutable $usedAt): void;

    public function getCreatedAt(): \DateTimeImmutable;
}
