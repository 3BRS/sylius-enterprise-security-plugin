<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Shop;

use Doctrine\ORM\EntityManagerInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use ThreeBRS\EnterpriseSecurityBundle\Controller\AbstractTwoFactorDisableController;
use ThreeBRS\EnterpriseSecurityBundle\RateLimit\RateLimitGuardInterface;
use ThreeBRS\EnterpriseSecurityBundle\TwoFactor\RecoveryCodeGeneratorInterface;
use ThreeBRS\EnterpriseSecurityBundle\TwoFactor\TwoFactorAuthShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\TwoFactorPageTargetPathTrait;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerRecoveryCodeRepositoryInterface;

class TwoFactorDisableController extends AbstractTwoFactorDisableController implements TwoFactorDisableControllerInterface
{
    use TwoFactorPageTargetPathTrait;

    public const CSRF_TOKEN_ID = 'three_brs_shop_two_factor_disable';

    public function __construct(
        protected EntityManagerInterface $entityManager,
        protected CustomerRecoveryCodeRepositoryInterface $recoveryCodeRepository,
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
        return $user instanceof ShopUserInterface && $user instanceof TwoFactorAuthShopUserInterface;
    }

    protected function disableTwoFactorAndCommit(UserInterface $user): void
    {
        \assert($user instanceof ShopUserInterface && $user instanceof TwoFactorAuthShopUserInterface);

        $user->setTotpSecret(null);
        $user->setTwoFactorEnabled(false);
        $user->bumpTrustedTokenVersion();
        $this->recoveryCodeRepository->deleteAllByShopUser($user);
        $this->entityManager->flush();
    }

    protected function getLoginUrl(): string
    {
        return $this->router->generate('sylius_shop_login');
    }

    protected function getRedirectAfterDisableUrl(): string
    {
        return $this->router->generate('sylius_shop_account_dashboard');
    }

    protected function verifyRecoveryCode(UserInterface $user, string $code): bool
    {
        \assert($user instanceof ShopUserInterface);

        return $this->recoveryCodeRepository->findUnusedByShopUserAndHash($user, $this->recoveryGenerator->hash($code)) !== null;
    }

    protected function getRateLimitGroup(): string
    {
        return 'customer';
    }

    protected function getRedirectAfterRefusedCodeUrl(): string
    {
        return $this->getTwoFactorPageUrl();
    }

    protected function getFirewallName(): string
    {
        return 'shop';
    }

    protected function getTwoFactorPageUrl(): string
    {
        return $this->router->generate('three_brs_shop_two_factor_setup');
    }
}
