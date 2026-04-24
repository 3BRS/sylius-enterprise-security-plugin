<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Model;

interface PasswordExpirationShopUserInterface
{
    public function getPasswordChangedAt(): ?\DateTimeImmutable;

    public function setPasswordChangedAt(?\DateTimeImmutable $passwordChangedAt): void;

    public function isForcePasswordChange(): bool;

    public function setForcePasswordChange(bool $forcePasswordChange): void;
}
