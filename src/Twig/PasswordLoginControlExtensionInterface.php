<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Twig;

interface PasswordLoginControlExtensionInterface
{
    /**
     * Whether email + password login is enabled for the given scope ('customer' / 'admin').
     */
    public function isEnabled(string $scope): bool;
}
