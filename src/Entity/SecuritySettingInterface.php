<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity;

interface SecuritySettingInterface
{
    public function getId(): ?int;

    public function getPath(): string;

    public function setPath(string $path): void;

    public function getScope(): string;

    public function setScope(string $scope): void;

    public function getValue(): mixed;

    public function setValue(mixed $value): void;

    public function getCreatedAt(): \DateTimeImmutable;

    public function getUpdatedAt(): \DateTimeImmutable;
}
