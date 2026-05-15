<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository;

use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\SecuritySettingInterface;

interface SecuritySettingRepositoryInterface
{
    public function findOneByPathAndScope(string $path, string $scope): ?SecuritySettingInterface;

    /**
     * @param list<string> $paths
     *
     * @return list<SecuritySettingInterface>
     */
    public function findManyByPathsAndScope(array $paths, string $scope): array;

    /**
     * @return list<SecuritySettingInterface>
     */
    public function findAllSettings(): array;

    public function truncateForFixtureReset(): void;
}
