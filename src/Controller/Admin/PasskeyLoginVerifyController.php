<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin;

use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use ThreeBRS\EnterpriseSecurityBundle\Controller\AbstractPasskeyLoginVerifyController;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Passkey\AdminPasskeyAssertionVerifierInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\AdminUserSessionLoginHandlerInterface;

class PasskeyLoginVerifyController extends AbstractPasskeyLoginVerifyController implements PasskeyLoginVerifyControllerInterface
{
    public function __construct(
        AdminPasskeyAssertionVerifierInterface $verifier,
        TokenStorageInterface $tokenStorage,
        RouterInterface $router,
        LoggerInterface $logger,
        protected AdminUserSessionLoginHandlerInterface $sessionLoginHandler,
        bool $enabled,
        UserCheckerInterface $userChecker,
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
        return 'admin';
    }

    protected function getDefaultRedirectUrl(): string
    {
        return $this->router->generate('sylius_admin_dashboard');
    }

    protected function getLogChannel(): string
    {
        return 'three_brs.passkey.admin';
    }

    protected function handlePostLogin(UserInterface $user, Request $request): void
    {
        if ($user instanceof AdminUserInterface) {
            $this->sessionLoginHandler->handle($user, $request);
        }
    }
}
