<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Fixture;

interface PasskeyFixtureInterface
{
    public function getName(): string;

    /** @param array<mixed> $options */
    public function load(array $options): void;
}
