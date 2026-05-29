<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Twig;

use Sylius\Component\Core\Model\ShopUserInterface;

interface PasswordLoginControlExtensionInterface
{
    /**
     * @return array{enabled: bool, allowed: bool, can_disable: bool}
     */
    public function customerStatus(ShopUserInterface $shopUser): array;
}
