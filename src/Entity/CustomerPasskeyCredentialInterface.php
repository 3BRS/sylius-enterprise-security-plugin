<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity;

use Sylius\Component\Core\Model\ShopUserInterface;

interface CustomerPasskeyCredentialInterface
{
    public function getId(): ?int;

    public function getShopUser(): ShopUserInterface;

    public function setShopUser(ShopUserInterface $shopUser): void;

    public function getCredentialId(): string;

    public function setCredentialId(string $credentialId): void;

    /** @return array<string, mixed> */
    public function getCredentialSource(): array;

    /** @param array<string, mixed> $credentialSource */
    public function setCredentialSource(array $credentialSource): void;

    public function getLabel(): string;

    public function setLabel(string $label): void;

    public function getCreatedAt(): \DateTimeImmutable;

    public function getLastUsedAt(): ?\DateTimeImmutable;

    public function setLastUsedAt(?\DateTimeImmutable $lastUsedAt): void;
}
