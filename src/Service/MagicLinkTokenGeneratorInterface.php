<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service;

interface MagicLinkTokenGeneratorInterface
{
    public function generatePlainToken(): string;

    public function hash(string $plainToken): string;
}
