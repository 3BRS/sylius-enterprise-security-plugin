<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Model;

interface PasswordPolicyInterface
{
    public function getMinLength(): int;

    public function getMaxLength(): ?int;

    public function isRequireUppercase(): bool;

    public function isRequireLowercase(): bool;

    public function isRequireNumbers(): bool;

    public function isRequireSpecialCharacters(): bool;
}
