<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity;

use Sylius\Component\Core\Model\AdminUserInterface;

interface AdminUserIpWhitelistInterface
{
    public function getId(): ?int;

    public function getAdminUser(): AdminUserInterface;

    public function setAdminUser(AdminUserInterface $adminUser): void;

    public function isEnabled(): bool;

    public function setEnabled(bool $enabled): void;

    /**
     * @return list<string>
     */
    public function getCidrs(): array;

    /**
     * @param list<string> $cidrs
     */
    public function setCidrs(array $cidrs): void;

    public function getCreatedAt(): \DateTimeImmutable;

    public function getUpdatedAt(): \DateTimeImmutable;

    public function touchUpdatedAt(): void;
}
