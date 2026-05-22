<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity;

use Sylius\Component\Core\Model\AdminUserInterface;
use ThreeBRS\EnterpriseSecurityBundle\IpRestriction\IpRestrictionRecordInterface;

interface AdminUserIpBlacklistInterface extends IpRestrictionRecordInterface
{
    public function getId(): ?int;

    public function getAdminUser(): AdminUserInterface;

    public function setAdminUser(AdminUserInterface $adminUser): void;

    public function setEnabled(bool $enabled): void;

    /**
     * @param list<string> $cidrs
     */
    public function setCidrs(array $cidrs): void;

    public function getCreatedAt(): \DateTimeImmutable;

    public function getUpdatedAt(): \DateTimeImmutable;

    public function touchUpdatedAt(): void;
}
