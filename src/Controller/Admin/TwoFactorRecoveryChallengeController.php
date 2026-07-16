<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin;

use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use ThreeBRS\EnterpriseSecurityBundle\Controller\AbstractTwoFactorRecoveryChallengeController;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\RecoveryCodeVerifierInterface;
use Twig\Environment;

class TwoFactorRecoveryChallengeController extends AbstractTwoFactorRecoveryChallengeController implements TwoFactorRecoveryChallengeControllerInterface
{
    public function __construct(
        protected RecoveryCodeVerifierInterface $recoveryCodeVerifier,
        TokenStorageInterface $tokenStorage,
        RouterInterface $router,
        Environment $twig,
        UserCheckerInterface $userChecker,
    ) {
        parent::__construct($tokenStorage, $router, $twig, $userChecker);
    }

    protected function isAcceptableUser(UserInterface $user): bool
    {
        return $user instanceof AdminUserInterface;
    }

    protected function verifyAndConsumeRecoveryCode(UserInterface $user, string $code): bool
    {
        \assert($user instanceof AdminUserInterface);

        return $this->recoveryCodeVerifier->verifyAndConsumeForAdminUser($user, $code);
    }

    protected function getFirewallName(): string
    {
        return 'admin';
    }

    protected function getDefaultRedirectUrl(): string
    {
        return $this->router->generate('sylius_admin_dashboard');
    }

    protected function getTemplate(): string
    {
        return '@ThreeBRSSyliusEnterpriseSecurityPlugin/Admin/TwoFactor/recovery_challenge.html.twig';
    }
}
