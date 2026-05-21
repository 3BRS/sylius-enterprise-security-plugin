<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use ThreeBRS\EnterpriseSecurityBundle\Controller\AbstractTwoFactorDisableController;
use ThreeBRS\EnterpriseSecurityBundle\TwoFactor\TwoFactorAuthAdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserRecoveryCodeRepositoryInterface;

class TwoFactorDisableController extends AbstractTwoFactorDisableController implements TwoFactorDisableControllerInterface
{
    public const CSRF_TOKEN_ID = 'three_brs_admin_two_factor_disable';

    public function __construct(
        protected EntityManagerInterface $entityManager,
        protected AdminUserRecoveryCodeRepositoryInterface $recoveryCodeRepository,
        TokenStorageInterface $tokenStorage,
        CsrfTokenManagerInterface $csrfTokenManager,
        RouterInterface $router,
    ) {
        parent::__construct($tokenStorage, $csrfTokenManager, $router);
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
}
