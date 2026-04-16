<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service;

interface RecoveryCodeGeneratorInterface
{
    /**
     * @return array<int, string> plain recovery codes (show to user once, store hash only)
     */
    public function generate(int $count): array;

    public function hash(string $plainCode): string;
}
