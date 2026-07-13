<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Shop;

use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use ThreeBRS\EnterpriseSecurityBundle\Challenge\CodeChallengeValidatorInterface;
use ThreeBRS\EnterpriseSecurityBundle\OAuth\OAuthLinkCodeGeneratorInterface;
use ThreeBRS\EnterpriseSecurityBundle\OAuth\OAuthUserInfoInterface;
use ThreeBRS\EnterpriseSecurityBundle\OAuth\SocialAccountLinkRecordInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\AbstractOAuthLinkCodeConfirmLinkController;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerSocialAccountLinkInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer\OAuthLinkCodeEmailManagerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerSocialAccountLinkRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\CustomerSessionLoginHandlerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\ShopSocialLoginHandlerInterface;
use Twig\Environment;

class OAuthConfirmLinkController extends AbstractOAuthLinkCodeConfirmLinkController implements OAuthConfirmLinkControllerInterface
{
    public function __construct(
        protected ShopSocialLoginHandlerInterface $handler,
        protected CustomerSocialAccountLinkRepositoryInterface $linkRepository,
        protected CustomerSessionLoginHandlerInterface $sessionLoginHandler,
        OAuthLinkCodeGeneratorInterface $codeGenerator,
        OAuthLinkCodeEmailManagerInterface $codeEmailManager,
        ClockInterface $clock,
        CsrfTokenManagerInterface $csrfTokenManager,
        CodeChallengeValidatorInterface $challengeValidator,
        TokenStorageInterface $tokenStorage,
        RouterInterface $router,
        Environment $twig,
        LoggerInterface $logger,
        UserCheckerInterface $userChecker,
    ) {
        parent::__construct($codeGenerator, $codeEmailManager, $clock, $csrfTokenManager, $challengeValidator, $tokenStorage, $router, $twig, $logger, $userChecker);
    }

    protected function getConfirmPendingSessionKey(): string
    {
        return OAuthCallbackController::CONFIRM_PENDING_SESSION_KEY;
    }

    protected function getFirewallName(): string
    {
        return 'shop';
    }

    protected function getLoginRoute(): string
    {
        return 'sylius_shop_login';
    }

    protected function getDashboardUrl(): string
    {
        return $this->router->generate('sylius_shop_account_dashboard');
    }

    protected function getTemplate(): string
    {
        return '@ThreeBRSSyliusEnterpriseSecurityPlugin/Shop/OAuth/confirm_link.html.twig';
    }

    protected function getAuditChannel(): string
    {
        return 'three_brs.social_login.shop';
    }

    protected function getAuditUserIdKey(): string
    {
        return 'user_id';
    }

    protected function findUserByEmail(string $email): ?UserInterface
    {
        return $this->handler->findUserByEmail($email);
    }

    protected function findExistingLink(string $provider, string $providerUserId): ?SocialAccountLinkRecordInterface
    {
        return $this->linkRepository->findByProviderAndProviderUserId($provider, $providerUserId);
    }

    protected function isLinkOwnedByUser(SocialAccountLinkRecordInterface $existing, UserInterface $user): bool
    {
        \assert($existing instanceof CustomerSocialAccountLinkInterface);
        \assert($user instanceof ShopUserInterface);

        return $existing->getShopUser()->getId() === $user->getId();
    }

    protected function linkExistingUser(UserInterface $user, OAuthUserInfoInterface $info): void
    {
        \assert($user instanceof ShopUserInterface);

        $this->handler->linkExistingUser($user, $info);
    }

    protected function handlePostLogin(UserInterface $user, Request $request): void
    {
        if ($user instanceof ShopUserInterface) {
            $this->sessionLoginHandler->handle($user, $request);
        }
    }
}
