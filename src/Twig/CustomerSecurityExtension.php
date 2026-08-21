<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Twig;

use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\EnterpriseSecurityBundle\Session\UserAgentInfo;
use ThreeBRS\EnterpriseSecurityBundle\Session\UserAgentParserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerDeletionRequestInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerDeletionRequestRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerSessionRepositoryInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CustomerSecurityExtension extends AbstractExtension implements CustomerSecurityExtensionInterface
{
    public function __construct(
        protected CustomerSessionRepositoryInterface $sessionRepository,
        protected UserAgentParserInterface $userAgentParser,
        protected CustomerDeletionRequestRepositoryInterface $deletionRequestRepository,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('three_brs_customer_active_sessions', $this->getActiveSessions(...)),
            new TwigFunction('three_brs_customer_login_history', $this->getLoginHistory(...)),
            new TwigFunction('three_brs_parse_user_agent', $this->parseUserAgent(...)),
            new TwigFunction('three_brs_customer_deletion_request', $this->getDeletionRequest(...)),
        ];
    }

    /**
     * A disabled account looks the same whether an administrator blocked it or the
     * customer asked to be erased, and only one of those is undone by the Unblock
     * button. The page needs to tell them apart.
     */
    public function getDeletionRequest(ShopUserInterface $user): ?CustomerDeletionRequestInterface
    {
        // getCustomer() is typed to the base Customer contract; the deletion request
        // is keyed on the Core one.
        $customer = $user->getCustomer();

        return $customer instanceof CustomerInterface
            ? $this->deletionRequestRepository->findActiveForCustomer($customer)
            : null;
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
