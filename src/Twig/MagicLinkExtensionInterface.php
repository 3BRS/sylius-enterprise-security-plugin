<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Twig;

interface MagicLinkExtensionInterface
{
    public function isEnabled(string $group): bool;
}
