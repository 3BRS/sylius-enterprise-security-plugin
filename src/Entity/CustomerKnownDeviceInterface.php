<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity;

use Sylius\Component\Core\Model\ShopUserInterface;

interface CustomerKnownDeviceInterface
{
    public function getId(): ?int;

    public function getShopUser(): ShopUserInterface;

    public function setShopUser(ShopUserInterface $shopUser): void;

    public function getFingerprint(): string;

    public function setFingerprint(string $fingerprint): void;

    public function getCreatedAt(): \DateTimeImmutable;
}
