<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin;

use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use ThreeBRS\EnterpriseSecurityBundle\Controller\AbstractOAuthCallbackController;
use ThreeBRS\EnterpriseSecurityBundle\OAuth\OAuthProviderRegistryInterface;
use ThreeBRS\EnterpriseSecurityBundle\OAuth\OAuthUserInfoInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\AdminSocialLoginHandlerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\AdminUserSessionLoginHandlerInterface;

class OAuthCallbackController extends AbstractOAuthCallbackController implements OAuthCallbackControllerInterface
{
    public const CONFIRM_PENDING_SESSION_KEY = 'three_brs_oauth_pending_admin';

    /**
     * @param UserProviderInterface<AdminUserInterface> $userProvider
     */
    public function __construct(
        OAuthProviderRegistryInterface $registry,
        protected AdminSocialLoginHandlerInterface $handler,
        RouterInterface $router,
        TokenStorageInterface $tokenStorage,
        Security $security,
        LoggerInterface $logger,
        protected AdminUserSessionLoginHandlerInterface $sessionLoginHandler,
        protected UserProviderInterface $userProvider,
    ) {
        parent::__construct($registry, $router, $tokenStorage, $security, $logger);
    }

    protected function getOAuthGroup(): string
    {
        return 'admin';
    }

    protected function getCallbackRouteName(): string
    {
        return 'three_brs_admin_oauth_callback';
    }

    protected function getFirewallName(): string
    {
        return 'admin';
    }

    protected function getStateSessionKey(): string
    {
        return OAuthInitiateController::STATE_SESSION_KEY;
    }

    protected function getIntentSessionKey(): string
    {
        return OAuthInitiateController::INTENT_SESSION_KEY;
    }

    protected function getConfirmPendingSessionKey(): string
    {
        return self::CONFIRM_PENDING_SESSION_KEY;
    }

    protected function getLoginRoute(): string
    {
        return 'sylius_admin_login';
    }

    protected function getDashboardUrl(): string
    {
        return $this->router->generate('sylius_admin_dashboard');
    }

    protected function getSocialAccountsRoute(): string
    {
        return 'three_brs_admin_social_accounts';
    }

    protected function getConfirmLinkRoute(): string
    {
        return 'three_brs_admin_oauth_confirm_link';
    }

    protected function getAuditChannel(): string
    {
        return 'three_brs.social_login.admin';
    }

    protected function getAuditUserIdKey(): string
    {
        return 'admin_id';
    }

    protected function isAcceptableCurrentUser(?UserInterface $user): bool
    {
        return $user instanceof AdminUserInterface;
    }

    protected function findExistingLinkUser(OAuthUserInfoInterface $info): ?UserInterface
    {
        return $this->handler->findExistingLinkUser($info);
    }

    protected function findUserByEmail(string $email): ?UserInterface
    {
        return $this->handler->findUserByEmail($email);
    }

    protected function findUserByIdentifier(string $identifier): ?UserInterface
    {
        try {
            return $this->userProvider->loadUserByIdentifier($identifier);
        } catch (UserNotFoundException) {
            return null;
        }
    }

    protected function canAutoRegister(OAuthUserInfoInterface $info): bool
    {
        return $this->handler->canAutoRegister($info);
    }

    protected function registerAndLink(OAuthUserInfoInterface $info): UserInterface
    {
        return $this->handler->registerAndLink($info);
    }

    protected function linkExistingUser(UserInterface $user, OAuthUserInfoInterface $info): void
    {
        \assert($user instanceof AdminUserInterface);

        $this->handler->linkExistingUser($user, $info);
    }

    protected function touchLastUsed(UserInterface $user, OAuthUserInfoInterface $info): void
    {
        \assert($user instanceof AdminUserInterface);

        $this->handler->touchLastUsed($user, $info);
    }

    protected function handlePostLogin(UserInterface $user, Request $request): void
    {
        if ($user instanceof AdminUserInterface) {
            $this->sessionLoginHandler->handle($user, $request);
        }
    }
}
