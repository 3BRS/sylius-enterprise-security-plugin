<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin;

use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\FirewallRedirectTrait;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\FlashHelperTrait;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\OAuth\OAuthUserInfo;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserSocialAccountLinkRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\AdminSocialLoginHandlerInterface;
use Twig\Environment;

class OAuthConfirmLinkController implements OAuthConfirmLinkControllerInterface
{
    use FirewallRedirectTrait;
    use FlashHelperTrait;

    protected const FIREWALL_NAME = 'admin';

    public function __construct(
        private AdminSocialLoginHandlerInterface $handler,
        private UserPasswordHasherInterface $passwordHasher,
        private TokenStorageInterface $tokenStorage,
        private RouterInterface $router,
        private Environment $twig,
        private AdminUserSocialAccountLinkRepositoryInterface $linkRepository,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $session = $request->getSession();
        $pending = $session->get(OAuthCallbackController::CONFIRM_PENDING_SESSION_KEY);

        if (!is_array($pending) || !isset($pending['email'], $pending['provider'], $pending['provider_user_id'])) {
            return new RedirectResponse($this->router->generate('sylius_admin_login'));
        }

        $email = (string) $pending['email'];
        $user = $this->handler->findUserByEmail($email);
        if ($user === null) {
            $session->remove(OAuthCallbackController::CONFIRM_PENDING_SESSION_KEY);

            return new RedirectResponse($this->router->generate('sylius_admin_login'));
        }

        $error = null;
        if ($request->isMethod('POST')) {
            $password = (string) $request->request->get('_password');
            if ($password === '' || !$this->passwordHasher->isPasswordValid($user, $password)) {
                $this->logger->info('three_brs.social_login.admin.confirm_link_failed', [
                    'provider' => (string) $pending['provider'],
                    'email' => $email,
                    'ip' => $request->getClientIp(),
                ]);
                $error = 'three_brs.ui.social_login.invalid_password';
            } else {
                $provider = (string) $pending['provider'];
                $providerUserId = (string) $pending['provider_user_id'];

                $existing = $this->linkRepository->findByProviderAndProviderUserId($provider, $providerUserId);
                if ($existing !== null) {
                    $session->remove(OAuthCallbackController::CONFIRM_PENDING_SESSION_KEY);

                    if ($existing->getAdminUser()->getId() !== $user->getId()) {
                        $this->addFlashMessage($request, 'error', 'three_brs.ui.social_login.already_linked_other_account');

                        return new RedirectResponse($this->router->generate('sylius_admin_login'));
                    }
                } else {
                    $info = new OAuthUserInfo(
                        $provider,
                        $providerUserId,
                        $email,
                        isset($pending['first_name']) ? (string) $pending['first_name'] : null,
                        isset($pending['last_name']) ? (string) $pending['last_name'] : null,
                    );

                    $this->handler->linkExistingUser($user, $info);
                    $session->remove(OAuthCallbackController::CONFIRM_PENDING_SESSION_KEY);
                }

                $this->logger->info('three_brs.social_login.admin.linked_via_confirm', [
                    'provider' => $provider,
                    'admin_id' => $user->getId(),
                    'email' => $email,
                    'ip' => $request->getClientIp(),
                ]);

                $this->authenticate($request, $user);
                $this->addFlashMessage($request, 'success', 'three_brs.ui.social_login.linked');

                return new RedirectResponse($this->resolveRedirectUrl($request, static::FIREWALL_NAME, $this->router->generate('sylius_admin_dashboard')));
            }
        }

        return new Response($this->twig->render(
            '@ThreeBRSSyliusEnterpriseSecurityPlugin/Admin/OAuth/confirm_link.html.twig',
            [
                'email' => $email,
                'provider' => (string) $pending['provider'],
                'error' => $error,
            ],
        ));
    }

    private function authenticate(Request $request, AdminUserInterface $user): void
    {
        $token = new PostAuthenticationToken($user, static::FIREWALL_NAME, $user->getRoles());
        $this->tokenStorage->setToken($token);

        if ($request->hasSession()) {
            $request->getSession()->set('_security_' . static::FIREWALL_NAME, serialize($token));
        }
    }
}
