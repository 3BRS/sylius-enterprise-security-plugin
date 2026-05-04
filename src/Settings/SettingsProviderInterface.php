<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings;

interface SettingsProviderInterface
{
    public function getBool(string $path, SettingsScope $scope): bool;

    public function getInt(string $path, SettingsScope $scope): int;

    public function getNullableInt(string $path, SettingsScope $scope): ?int;

    public function getString(string $path, SettingsScope $scope): string;

    public function getNullableString(string $path, SettingsScope $scope): ?string;

    /**
     * @return list<string>
     */
    public function getStringList(string $path, SettingsScope $scope): array;

    public function get(string $path, SettingsScope $scope): mixed;

    public function has(string $path, SettingsScope $scope): bool;

    public function refresh(): void;
}
