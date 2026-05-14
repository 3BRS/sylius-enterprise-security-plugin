<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity;

use Sylius\Component\Core\Model\ShopUserInterface;

interface CustomerRecoveryCodeInterface
{
    public function getId(): ?int;

    public function getShopUser(): ShopUserInterface;

    public function setShopUser(ShopUserInterface $shopUser): void;

    public function getCodeHash(): string;

    public function setCodeHash(string $codeHash): void;

    public function getCreatedAt(): \DateTimeImmutable;

    public function getUsedAt(): ?\DateTimeImmutable;

    public function setUsedAt(?\DateTimeImmutable $usedAt): void;

    public function isUsed(): bool;
}
