<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Shop;

use Psr\Log\LoggerInterface;
use Sylius\Bundle\UserBundle\Event\UserEvent;
use Sylius\Bundle\UserBundle\UserEvents;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use ThreeBRS\EnterpriseSecurityBundle\Controller\AbstractPasskeyLoginVerifyController;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Passkey\CustomerPasskeyAssertionVerifierInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\CustomerSessionLoginHandlerInterface;

class PasskeyLoginVerifyController extends AbstractPasskeyLoginVerifyController implements PasskeyLoginVerifyControllerInterface
{
    public function __construct(
        CustomerPasskeyAssertionVerifierInterface $verifier,
        TokenStorageInterface $tokenStorage,
        RouterInterface $router,
        LoggerInterface $logger,
        protected CustomerSessionLoginHandlerInterface $sessionLoginHandler,
        bool $enabled,
        UserCheckerInterface $userChecker,
        protected EventDispatcherInterface $eventDispatcher,
    ) {
        parent::__construct(
            $verifier,
            $tokenStorage,
            $router,
            $logger,
            $enabled,
            $userChecker,
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
            $this->eventDispatcher->dispatch(new UserEvent($user), UserEvents::SECURITY_IMPLICIT_LOGIN);
        }
    }
}
