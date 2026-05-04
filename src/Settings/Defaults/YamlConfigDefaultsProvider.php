<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings\Defaults;

use ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings\SettingsScope;

class YamlConfigDefaultsProvider implements SettingsDefaultsProviderInterface
{
    /**
     * @param array<string, array<string, mixed>> $defaults scope.value => path => value
     */
    public function __construct(
        protected array $defaults,
    ) {
    }

    public function get(string $path, SettingsScope $scope): mixed
    {
        return $this->defaults[$scope->value][$path] ?? null;
    }

    public function has(string $path, SettingsScope $scope): bool
    {
        return array_key_exists($path, $this->defaults[$scope->value] ?? []);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->defaults;
    }
}
