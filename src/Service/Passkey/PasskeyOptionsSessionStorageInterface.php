<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Passkey;

interface PasskeyOptionsSessionStorageInterface
{
    public function store(string $key, string $serialized): void;

    public function consume(string $key): ?string;
}
