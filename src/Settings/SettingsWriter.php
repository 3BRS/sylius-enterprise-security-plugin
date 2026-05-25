<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\Exception\ConcurrentSettingsWriteException;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsProviderInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsWriterInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\SecuritySetting;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\SecuritySettingInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\SecuritySettingRepositoryInterface;

class SettingsWriter implements SettingsWriterInterface
{
    public function __construct(
        protected SecuritySettingRepositoryInterface $repository,
        protected EntityManagerInterface $entityManager,
        protected SettingsProviderInterface $provider,
    ) {
    }

    public function set(string $path, SettingsScope $scope, mixed $value): void
    {
        $setting = $this->repository->findOneByPathAndScope($path, $scope->value);

        if ($setting === null) {
            $setting = new SecuritySetting();
            $setting->setPath($path);
            $setting->setScope($scope->value);
            $this->entityManager->persist($setting);
        }

        $setting->setValue($value);
    }

    /**
     * @param array<string, mixed> $values
     */
    public function setMany(SettingsScope $scope, array $values): void
    {
        $existing = $this->loadExistingMap($scope, array_keys($values));

        foreach ($values as $path => $value) {
            $setting = $existing[$path] ?? null;
            if ($setting === null) {
                $setting = new SecuritySetting();
                $setting->setPath($path);
                $setting->setScope($scope->value);
                $this->entityManager->persist($setting);
            }
            $setting->setValue($value);
        }
    }

    public function flush(): void
    {
        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $exception) {
            // Concurrent admin committed the same (path, scope) row between our
            // find() and flush(). The unit of work is now broken — we can't
            // safely re-apply our writes without re-reading the conflicting
            // row, and the caller already constructed entities that are stale.
            // Reset state, refresh the cache, and surface a typed exception so
            // the caller knows our changes were dropped (instead of silently
            // showing a "saved" flash on a no-op).
            $this->entityManager->clear();
            $this->provider->refresh();

            throw new ConcurrentSettingsWriteException(
                'Settings were not saved because another administrator updated the same values concurrently. Reload the page and try again.',
                previous: $exception,
            );
        }

        $this->provider->refresh();
    }

    /**
     * @param list<string> $paths
     *
     * @return array<string, SecuritySettingInterface>
     */
    protected function loadExistingMap(SettingsScope $scope, array $paths): array
    {
        $map = [];
        foreach ($this->repository->findManyByPathsAndScope($paths, $scope->value) as $setting) {
            $map[$setting->getPath()] = $setting;
        }

        return $map;
    }
}
