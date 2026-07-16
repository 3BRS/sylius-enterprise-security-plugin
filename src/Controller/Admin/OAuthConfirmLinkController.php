<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin;

use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
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
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserSocialAccountLinkInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer\OAuthLinkCodeEmailManagerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserSocialAccountLinkRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\AdminSocialLoginHandlerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\AdminUserSessionLoginHandlerInterface;
use Twig\Environment;

class OAuthConfirmLinkController extends AbstractOAuthLinkCodeConfirmLinkController implements OAuthConfirmLinkControllerInterface
{
    public function __construct(
        protected AdminSocialLoginHandlerInterface $handler,
        protected AdminUserSocialAccountLinkRepositoryInterface $linkRepository,
        protected AdminUserSessionLoginHandlerInterface $sessionLoginHandler,
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
        return 'admin';
    }

    protected function getLoginRoute(): string
    {
        return 'sylius_admin_login';
    }

    protected function getDashboardUrl(): string
    {
        return $this->router->generate('sylius_admin_dashboard');
    }

    protected function getTemplate(): string
    {
        return '@ThreeBRSSyliusEnterpriseSecurityPlugin/Admin/OAuth/confirm_link.html.twig';
    }

    protected function getAuditChannel(): string
    {
        return 'three_brs.social_login.admin';
    }

    protected function getAuditUserIdKey(): string
    {
        return 'admin_id';
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
        \assert($existing instanceof AdminUserSocialAccountLinkInterface);
        \assert($user instanceof AdminUserInterface);

        return $existing->getAdminUser()->getId() === $user->getId();
    }

    protected function linkExistingUser(UserInterface $user, OAuthUserInfoInterface $info): void
    {
        \assert($user instanceof AdminUserInterface);

        $this->handler->linkExistingUser($user, $info);
    }

    protected function handlePostLogin(UserInterface $user, Request $request): void
    {
        if ($user instanceof AdminUserInterface) {
            $this->sessionLoginHandler->handle($user, $request);
        }
    }
}
