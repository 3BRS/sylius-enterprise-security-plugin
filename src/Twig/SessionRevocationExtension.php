<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Twig;

use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserSessionRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerSessionRepositoryInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * The sessions pages list active sessions only, but an expired session can still be signed in and
 * "sign out other sessions" revokes it too, so the button follows every session not revoked yet.
 */
class SessionRevocationExtension extends AbstractExtension implements SessionRevocationExtensionInterface
{
    public function __construct(
        protected CustomerSessionRepositoryInterface $customerSessionRepository,
        protected AdminUserSessionRepositoryInterface $adminUserSessionRepository,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('three_brs_has_other_unrevoked_sessions', $this->hasOtherUnrevokedSessions(...)),
        ];
    }

    public function hasOtherUnrevokedSessions(?UserInterface $user, string $currentSessionId): bool
    {
        $sessions = match (true) {
            $user instanceof ShopUserInterface => $this->customerSessionRepository->findUnrevokedForShopUser($user),
            $user instanceof AdminUserInterface => $this->adminUserSessionRepository->findUnrevokedForAdminUser($user),
            default => [],
        };

        foreach ($sessions as $session) {
            if ($session->getSessionId() !== $currentSessionId) {
                return true;
            }
        }

        return false;
    }
}
