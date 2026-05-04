<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository;

use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\SecuritySettingInterface;

interface SecuritySettingRepositoryInterface
{
    public function findOneByPathAndScope(string $path, string $scope): ?SecuritySettingInterface;

    /**
     * @return list<SecuritySettingInterface>
     */
    public function findAllSettings(): array;

    public function deleteAll(): void;
}
