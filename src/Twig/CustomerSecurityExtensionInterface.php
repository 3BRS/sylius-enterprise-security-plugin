<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Twig;

use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerSessionInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\UserAgentInfo;

interface CustomerSecurityExtensionInterface
{
    /**
     * @return list<CustomerSessionInterface>
     */
    public function getActiveSessions(ShopUserInterface $user): array;

    /**
     * @return list<CustomerSessionInterface>
     */
    public function getLoginHistory(ShopUserInterface $user, int $limit = 20): array;

    public function parseUserAgent(?string $userAgent): UserAgentInfo;
}
