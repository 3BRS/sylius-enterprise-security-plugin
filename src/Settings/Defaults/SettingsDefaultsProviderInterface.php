<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings\Defaults;

use ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings\SettingsScope;

interface SettingsDefaultsProviderInterface
{
    public function get(string $path, SettingsScope $scope): mixed;

    public function has(string $path, SettingsScope $scope): bool;

    /**
     * @return array<string, array<string, mixed>>  scope.value => path.value => default
     */
    public function all(): array;
}
