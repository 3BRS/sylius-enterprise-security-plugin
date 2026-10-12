<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use ThreeBRS\EnterpriseSecurityBundle\Controller\AbstractTwoFactorDisableController;
use ThreeBRS\EnterpriseSecurityBundle\RateLimit\RateLimitGuardInterface;
use ThreeBRS\EnterpriseSecurityBundle\TwoFactor\RecoveryCodeGeneratorInterface;
use ThreeBRS\EnterpriseSecurityBundle\TwoFactor\TwoFactorAuthAdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\TwoFactorPageTargetPathTrait;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserRecoveryCodeRepositoryInterface;

class TwoFactorDisableController extends AbstractTwoFactorDisableController implements TwoFactorDisableControllerInterface
{
    use TwoFactorPageTargetPathTrait;

    public const CSRF_TOKEN_ID = 'three_brs_admin_two_factor_disable';

    public function __construct(
        protected EntityManagerInterface $entityManager,
        protected AdminUserRecoveryCodeRepositoryInterface $recoveryCodeRepository,
        TokenStorageInterface $tokenStorage,
        CsrfTokenManagerInterface $csrfTokenManager,
        RouterInterface $router,
        protected RecoveryCodeGeneratorInterface $recoveryGenerator,
        ?TotpAuthenticatorInterface $totpAuthenticator,
        RateLimitGuardInterface $rateLimitGuard,
    ) {
        parent::__construct($tokenStorage, $csrfTokenManager, $router, $totpAuthenticator, $rateLimitGuard);
    }

    protected function getCsrfTokenId(): string
    {
        return self::CSRF_TOKEN_ID;
    }

    protected function isTwoFactorCapableUser(UserInterface $user): bool
    {
        return $user instanceof AdminUserInterface && $user instanceof TwoFactorAuthAdminUserInterface;
    }

    protected function disableTwoFactorAndCommit(UserInterface $user): void
    {
        \assert($user instanceof AdminUserInterface && $user instanceof TwoFactorAuthAdminUserInterface);

        $user->setTotpSecret(null);
        $user->setTwoFactorEnabled(false);
        $user->bumpTrustedTokenVersion();
        $this->recoveryCodeRepository->deleteAllByAdminUser($user);
        $this->entityManager->flush();
    }

    protected function getLoginUrl(): string
    {
        return $this->router->generate('sylius_admin_login');
    }

    protected function getRedirectAfterDisableUrl(): string
    {
        return $this->router->generate('sylius_admin_dashboard');
    }

    protected function verifyRecoveryCode(UserInterface $user, string $code): bool
    {
        \assert($user instanceof AdminUserInterface);

        return $this->recoveryCodeRepository->findUnusedByAdminUserAndHash($user, $this->recoveryGenerator->hash($code)) !== null;
    }

    protected function getRateLimitGroup(): string
    {
        return 'admin';
    }

    protected function getRedirectAfterRefusedCodeUrl(): string
    {
        return $this->getTwoFactorPageUrl();
    }

    protected function getFirewallName(): string
    {
        return 'admin';
    }

    protected function getTwoFactorPageUrl(): string
    {
        return $this->router->generate('three_brs_admin_two_factor_setup');
    }
}
