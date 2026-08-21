<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Twig;

use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\EnterpriseSecurityBundle\Session\UserAgentInfo;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerDeletionRequestInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerSessionInterface;

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

    public function getDeletionRequest(ShopUserInterface $user): ?CustomerDeletionRequestInterface;

    public function parseUserAgent(?string $userAgent): UserAgentInfo;
}
