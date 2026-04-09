<?php

declare(strict_types=1);

namespace ThreeBRS\EnterpriseSecurityBundle\Model;

class PasswordPolicy implements PasswordPolicyInterface
{
    public function __construct(
        private int $minLength,
        private ?int $maxLength,
        private bool $requireUppercase,
        private bool $requireLowercase,
        private bool $requireNumbers,
        private bool $requireSpecialCharacters,
    ) {
    }

    public function getMinLength(): int
    {
        return $this->minLength;
    }

    public function getMaxLength(): ?int
    {
        return $this->maxLength;
    }

    public function isRequireUppercase(): bool
    {
        return $this->requireUppercase;
    }

    public function isRequireLowercase(): bool
    {
        return $this->requireLowercase;
    }

    public function isRequireNumbers(): bool
    {
        return $this->requireNumbers;
    }

    public function isRequireSpecialCharacters(): bool
    {
        return $this->requireSpecialCharacters;
    }
}
