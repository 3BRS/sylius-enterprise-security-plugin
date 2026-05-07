<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings;

use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\SecuritySettingRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings\Defaults\SettingsDefaultsProviderInterface;

class SettingsProvider implements SettingsProviderInterface
{
    /** @var array<string, array<string, mixed>>|null */
    protected ?array $cache = null;

    public function __construct(
        protected SecuritySettingRepositoryInterface $repository,
        protected SettingsDefaultsProviderInterface $defaultsProvider,
    ) {
    }

    public function getBool(string $path, SettingsScope $scope): bool
    {
        return (bool) $this->get($path, $scope);
    }

    public function getInt(string $path, SettingsScope $scope): int
    {
        return (int) $this->get($path, $scope);
    }

    public function getNullableInt(string $path, SettingsScope $scope): ?int
    {
        $value = $this->get($path, $scope);

        return $value === null ? null : (int) $value;
    }

    public function getString(string $path, SettingsScope $scope): string
    {
        return (string) $this->get($path, $scope);
    }

    public function get(string $path, SettingsScope $scope): mixed
    {
        $scopeMap = $this->load()[$scope->value] ?? [];

        if (array_key_exists($path, $scopeMap)) {
            return $scopeMap[$path];
        }

        return $this->defaultsProvider->get($path, $scope);
    }

    public function refresh(): void
    {
        $this->cache = null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function load(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $cache = [
            SettingsScope::CUSTOMER->value => [],
            SettingsScope::ADMIN->value => [],
            SettingsScope::GLOBAL->value => [],
        ];

        foreach ($this->repository->findAllSettings() as $setting) {
            $cache[$setting->getScope()][$setting->getPath()] = $setting->getValue();
        }

        $this->cache = $cache;

        return $cache;
    }
}
