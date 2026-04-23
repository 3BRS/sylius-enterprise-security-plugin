<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity;

use Sylius\Component\Core\Model\AdminUserInterface;

interface AdminUserSocialAccountLinkInterface
{
    public function getId(): ?int;

    public function getAdminUser(): AdminUserInterface;

    public function setAdminUser(AdminUserInterface $adminUser): void;

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
