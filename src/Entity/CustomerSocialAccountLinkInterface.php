<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity;

use Sylius\Component\Core\Model\ShopUserInterface;

interface CustomerSocialAccountLinkInterface
{
    public function getId(): ?int;

    public function getShopUser(): ShopUserInterface;

    public function setShopUser(ShopUserInterface $shopUser): void;

    public function getProvider(): string;

    public function setProvider(string $provider): void;

    public function getProviderUserId(): string;

    public function setProviderUserId(string $providerUserId): void;

    public function getEmail(): ?string;

    public function setEmail(?string $email): void;

    public function getLinkedAt(): \DateTimeImmutable;

    public function getLastUsedAt(): ?\DateTimeImmutable;

    public function setLastUsedAt(?\DateTimeImmutable $lastUsedAt): void;
}
