<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity;

use Sylius\Component\Core\Model\AdminUserInterface;

interface AdminUserKnownDeviceInterface
{
    public function getId(): ?int;

    public function getAdminUser(): AdminUserInterface;

    public function setAdminUser(AdminUserInterface $adminUser): void;

    public function getFingerprint(): string;

    public function setFingerprint(string $fingerprint): void;

    public function getCreatedAt(): \DateTimeImmutable;
}
