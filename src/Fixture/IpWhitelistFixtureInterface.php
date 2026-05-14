<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Fixture;

interface IpWhitelistFixtureInterface
{
    public function getName(): string;

    /**
     * @param array<mixed> $options
     */
    public function load(array $options): void;
}
