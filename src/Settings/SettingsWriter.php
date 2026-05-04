<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings;

use Doctrine\ORM\EntityManagerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\SecuritySetting;
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
        foreach ($values as $path => $value) {
            $this->set($path, $scope, $value);
        }
    }

    public function flush(): void
    {
        $this->entityManager->flush();
        $this->provider->refresh();
    }
}
