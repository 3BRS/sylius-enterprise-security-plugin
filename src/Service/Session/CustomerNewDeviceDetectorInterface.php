<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session;

use Sylius\Component\Core\Model\ShopUserInterface;

interface CustomerNewDeviceDetectorInterface
{
    /**
     * Returns true if the (user, fingerprint) combination has not been seen before
     * and persists the new fingerprint as a known device.
     */
    public function checkAndRemember(ShopUserInterface $user, string $fingerprint): bool;
}
