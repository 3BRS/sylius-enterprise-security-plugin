<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity;

use Sylius\Component\Core\Model\ShopUserInterface;

interface CustomerSessionInterface
{
    public function getId(): ?int;

    public function getShopUser(): ShopUserInterface;

    public function setShopUser(ShopUserInterface $shopUser): void;

    public function getSessionId(): string;

    public function setSessionId(string $sessionId): void;

    public function getUserAgent(): ?string;

    public function setUserAgent(?string $userAgent): void;

    public function getIpAddress(): ?string;

    public function setIpAddress(?string $ipAddress): void;

    public function getCountry(): ?string;

    public function setCountry(?string $country): void;

    public function getCity(): ?string;

    public function setCity(?string $city): void;

    public function getCreatedAt(): \DateTimeImmutable;

    public function getLastActivityAt(): \DateTimeImmutable;

    public function setLastActivityAt(\DateTimeImmutable $lastActivityAt): void;

    public function getRevokedAt(): ?\DateTimeImmutable;

    public function setRevokedAt(?\DateTimeImmutable $revokedAt): void;

    public function isRevoked(): bool;
}
