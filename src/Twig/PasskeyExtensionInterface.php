<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Twig;

interface PasskeyExtensionInterface
{
    public function isEnabled(string $group): bool;
}
