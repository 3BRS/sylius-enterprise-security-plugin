<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity;

use Sylius\Component\Core\Model\ShopUserInterface;

interface CustomerMagicLinkTokenInterface
{
    public function getId(): ?int;

    public function getShopUser(): ShopUserInterface;

    public function setShopUser(ShopUserInterface $shopUser): void;

    public function getTokenHash(): string;

    public function setTokenHash(string $tokenHash): void;

    public function getExpiresAt(): \DateTimeImmutable;

    public function setExpiresAt(\DateTimeImmutable $expiresAt): void;

    public function getUsedAt(): ?\DateTimeImmutable;

    public function setUsedAt(?\DateTimeImmutable $usedAt): void;

    public function getCreatedAt(): \DateTimeImmutable;
}
