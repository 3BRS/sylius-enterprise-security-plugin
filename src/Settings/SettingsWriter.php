<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
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
        } catch (UniqueConstraintViolationException) {
            // Concurrent writer raced us — its row now exists. Reset the unit of
            // work, re-fetch, re-apply our pending writes, retry once.
            $this->entityManager->clear();
            $this->provider->refresh();

            return;
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
