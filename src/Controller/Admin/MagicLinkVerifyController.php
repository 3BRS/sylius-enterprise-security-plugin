<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use ThreeBRS\EnterpriseSecurityBundle\Controller\AbstractMagicLinkVerifyController;
use ThreeBRS\EnterpriseSecurityBundle\MagicLink\MagicLinkRecordInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserMagicLinkTokenInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\AdminUserMagicLinkTokenVerifierInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\AdminUserSessionLoginHandlerInterface;

class MagicLinkVerifyController extends AbstractMagicLinkVerifyController implements MagicLinkVerifyControllerInterface
{
    public function __construct(
        AdminUserMagicLinkTokenVerifierInterface $verifier,
        protected EntityManagerInterface $entityManager,
        TokenStorageInterface $tokenStorage,
        RouterInterface $router,
        ClockInterface $clock,
        LoggerInterface $logger,
        protected AdminUserSessionLoginHandlerInterface $sessionLoginHandler,
        bool $enabled,
        UserCheckerInterface $userChecker,
    ) {
        parent::__construct(
            $verifier,
            $tokenStorage,
            $router,
            $clock,
            $logger,
            $enabled,
            $userChecker,
        );
    }

    protected function isFullyAuthenticatedUser(?TokenInterface $token): bool
    {
        return $token !== null &&
            !$token instanceof TwoFactorTokenInterface &&
            $token->getUser() instanceof AdminUserInterface;
    }

    protected function getUserFromMagicLink(MagicLinkRecordInterface $magicLink): UserInterface
    {
        \assert($magicLink instanceof AdminUserMagicLinkTokenInterface);

        return $magicLink->getAdminUser();
    }

    protected function commitMagicLinkUsage(MagicLinkRecordInterface $magicLink): void
    {
        $this->entityManager->flush();
    }

    protected function getFirewallName(): string
    {
        return 'admin';
    }

    protected function getDefaultRedirectUrl(): string
    {
        return $this->router->generate('sylius_admin_dashboard');
    }

    protected function getMagicLinkRequestUrl(): string
    {
        return $this->router->generate('three_brs_admin_magic_link_request');
    }

    protected function getLogChannel(): string
    {
        return 'three_brs.magic_link.admin';
    }

    protected function handlePostLogin(UserInterface $user, Request $request): void
    {
        if ($user instanceof AdminUserInterface) {
            $this->sessionLoginHandler->handle($user, $request);
        }
    }
}
