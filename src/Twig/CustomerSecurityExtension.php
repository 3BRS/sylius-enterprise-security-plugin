<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Twig;

use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerSessionRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\UserAgentInfo;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\UserAgentParserInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CustomerSecurityExtension extends AbstractExtension implements CustomerSecurityExtensionInterface
{
    public function __construct(
        protected CustomerSessionRepositoryInterface $sessionRepository,
        protected UserAgentParserInterface $userAgentParser,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('three_brs_customer_active_sessions', $this->getActiveSessions(...)),
            new TwigFunction('three_brs_customer_login_history', $this->getLoginHistory(...)),
            new TwigFunction('three_brs_parse_user_agent', $this->parseUserAgent(...)),
        ];
    }

    public function getActiveSessions(ShopUserInterface $user): array
    {
        return $this->sessionRepository->findActiveForShopUser($user);
    }

    public function getLoginHistory(ShopUserInterface $user, int $limit = 20): array
    {
        return $this->sessionRepository->findAllForShopUser($user, $limit);
    }

    public function parseUserAgent(?string $userAgent): UserAgentInfo
    {
        return $this->userAgentParser->parse($userAgent);
    }
}
