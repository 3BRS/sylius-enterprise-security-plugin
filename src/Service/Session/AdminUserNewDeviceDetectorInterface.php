<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session;

use Sylius\Component\Core\Model\AdminUserInterface;

interface AdminUserNewDeviceDetectorInterface
{
    /**
     * Returns true if the (user, fingerprint) combination has not been seen before
     * and persists the new fingerprint as a known device.
     */
    public function checkAndRemember(AdminUserInterface $user, string $fingerprint): bool;
}
