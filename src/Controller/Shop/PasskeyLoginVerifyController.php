<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Shop;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Scheb\TwoFactorBundle\Security\Http\Authentication\AuthenticationRequiredHandlerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use ThreeBRS\EnterpriseSecurityBundle\Controller\AbstractPasskeyLoginVerifyController;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Passkey\CustomerPasskeyAssertionVerifierInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\CustomerSessionLoginHandlerInterface;

class PasskeyLoginVerifyController extends AbstractPasskeyLoginVerifyController implements PasskeyLoginVerifyControllerInterface
{
    public function __construct(
        CustomerPasskeyAssertionVerifierInterface $verifier,
        TokenStorageInterface $tokenStorage,
        EventDispatcherInterface $eventDispatcher,
        AuthenticationRequiredHandlerInterface $twoFactorHandler,
        RouterInterface $router,
        LoggerInterface $logger,
        protected CustomerSessionLoginHandlerInterface $sessionLoginHandler,
        bool $enabled,
        bool $skipTwoFactorWhenUserVerified,
    ) {
        parent::__construct(
            $verifier,
            $tokenStorage,
            $eventDispatcher,
            $twoFactorHandler,
            $router,
            $logger,
            $enabled,
            $skipTwoFactorWhenUserVerified,
        );
    }

    protected function getFirewallName(): string
    {
        return 'shop';
    }

    protected function getDefaultRedirectUrl(): string
    {
        return $this->router->generate('sylius_shop_account_dashboard');
    }

    protected function getLogChannel(): string
    {
        return 'three_brs.passkey.shop';
    }

    protected function handlePostLogin(UserInterface $user, Request $request): void
    {
        if ($user instanceof ShopUserInterface) {
            $this->sessionLoginHandler->handle($user, $request);
        }
    }
}
