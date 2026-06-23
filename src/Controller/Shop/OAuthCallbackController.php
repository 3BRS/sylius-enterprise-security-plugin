<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Shop;

use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
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
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\CustomerSessionLoginHandlerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\ShopSocialLoginHandlerInterface;

class OAuthCallbackController extends AbstractOAuthCallbackController implements OAuthCallbackControllerInterface
{
    public const CONFIRM_PENDING_SESSION_KEY = 'three_brs_oauth_pending_customer';

    /**
     * @param UserProviderInterface<ShopUserInterface> $userProvider
     */
    public function __construct(
        OAuthProviderRegistryInterface $registry,
        protected ShopSocialLoginHandlerInterface $handler,
        RouterInterface $router,
        TokenStorageInterface $tokenStorage,
        Security $security,
        LoggerInterface $logger,
        protected CustomerSessionLoginHandlerInterface $sessionLoginHandler,
        protected UserProviderInterface $userProvider,
    ) {
        parent::__construct($registry, $router, $tokenStorage, $security, $logger);
    }

    protected function getOAuthGroup(): string
    {
        return 'customer';
    }

    protected function getCallbackRouteName(): string
    {
        return 'three_brs_shop_oauth_callback';
    }

    protected function getFirewallName(): string
    {
        return 'shop';
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
        return 'sylius_shop_login';
    }

    protected function getDashboardUrl(): string
    {
        return $this->router->generate('sylius_shop_account_dashboard');
    }

    protected function getSocialAccountsRoute(): string
    {
        return 'three_brs_shop_social_accounts';
    }

    protected function getConfirmLinkRoute(): string
    {
        return 'three_brs_shop_oauth_confirm_link';
    }

    protected function getAuditChannel(): string
    {
        return 'three_brs.social_login.shop';
    }

    protected function getAuditUserIdKey(): string
    {
        return 'user_id';
    }

    protected function isAcceptableCurrentUser(?UserInterface $user): bool
    {
        return $user instanceof ShopUserInterface;
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
        \assert($user instanceof ShopUserInterface);

        $this->handler->linkExistingUser($user, $info);
    }

    protected function touchLastUsed(UserInterface $user, OAuthUserInfoInterface $info): void
    {
        \assert($user instanceof ShopUserInterface);

        $this->handler->touchLastUsed($user, $info);
    }

    protected function handlePostLogin(UserInterface $user, Request $request): void
    {
        if ($user instanceof ShopUserInterface) {
            $this->sessionLoginHandler->handle($user, $request);
        }
    }
}
