<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity;

use Sylius\Component\Core\Model\ShopUserInterface;

interface CustomerPasswordHistoryInterface
{
    public function getId(): ?int;

    public function getShopUser(): ShopUserInterface;

    public function setShopUser(ShopUserInterface $shopUser): void;

    public function getPasswordHash(): string;

    public function setPasswordHash(string $passwordHash): void;

    public function getCreatedAt(): \DateTimeImmutable;
}
