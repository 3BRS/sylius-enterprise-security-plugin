<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Shop;

use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
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
    ) {
        parent::__construct($tokenStorage, $router, $twig);
    }

    protected function isAcceptableUser(UserInterface $user): bool
    {
        return $user instanceof ShopUserInterface;
    }

    protected function verifyAndConsumeRecoveryCode(UserInterface $user, string $code): bool
    {
        \assert($user instanceof ShopUserInterface);

        return $this->recoveryCodeVerifier->verifyAndConsumeForShopUser($user, $code);
    }

    protected function getFirewallName(): string
    {
        return 'shop';
    }

    protected function getDefaultRedirectUrl(): string
    {
        return $this->router->generate('sylius_shop_homepage');
    }

    protected function getTemplate(): string
    {
        return '@ThreeBRSSyliusEnterpriseSecurityPlugin/Shop/TwoFactor/recovery_challenge.html.twig';
    }
}
