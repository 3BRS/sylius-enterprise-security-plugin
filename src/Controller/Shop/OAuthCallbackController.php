<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Shop;

use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;
use ThreeBRS\EnterpriseSecurityBundle\OAuth\Exception\OAuthProviderException;
use ThreeBRS\EnterpriseSecurityBundle\OAuth\OAuthProviderRegistryInterface;
use ThreeBRS\EnterpriseSecurityBundle\OAuth\OAuthUserInfoInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\FirewallRedirectTrait;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\FlashHelperTrait;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\CustomerSessionLoginHandlerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\ShopSocialLoginHandlerInterface;

class OAuthCallbackController implements OAuthCallbackControllerInterface
{
    use FirewallRedirectTrait;
    use FlashHelperTrait;

    public const CONFIRM_PENDING_SESSION_KEY = 'three_brs_oauth_pending_customer';

    protected const FIREWALL_NAME = 'shop';

    public function __construct(
        protected OAuthProviderRegistryInterface $registry,
        protected ShopSocialLoginHandlerInterface $handler,
        protected RouterInterface $router,
        protected TokenStorageInterface $tokenStorage,
        protected Security $security,
        protected LoggerInterface $logger,
        protected CustomerSessionLoginHandlerInterface $sessionLoginHandler,
    ) {
    }

    public function __invoke(Request $request, string $provider): Response
    {
        if (!$this->registry->has($provider)) {
            throw new OAuthProviderException(sprintf('Unknown OAuth provider "%s".', $provider));
        }

        $oauthProvider = $this->registry->get($provider);

        $session = $request->getSession();
        $expectedState = (string) $session->get(OAuthInitiateController::STATE_SESSION_KEY . '_' . $provider, '');
        $session->remove(OAuthInitiateController::STATE_SESSION_KEY . '_' . $provider);
        $intent = (string) $session->get(OAuthInitiateController::INTENT_SESSION_KEY, 'login');
        $session->remove(OAuthInitiateController::INTENT_SESSION_KEY);

        $redirectUri = $this->router->generate(
            'three_brs_shop_oauth_callback',
            ['provider' => $provider],
            RouterInterface::ABSOLUTE_URL,
        );

        try {
            $info = $oauthProvider->fetchUserInfo($request, $redirectUri, $expectedState, 'customer');
        } catch (OAuthProviderException $exception) {
            $this->addFlashMessage($request, 'error', $exception->getMessage());

            return new RedirectResponse($this->router->generate('sylius_shop_login'));
        }

        if ($intent === 'link') {
            return $this->handleLinkIntent($request, $info);
        }

        return $this->handleLoginIntent($request, $info);
    }

    protected function handleLinkIntent(Request $request, OAuthUserInfoInterface $info): Response
    {
        $currentUser = $this->security->getUser();
        if (!$currentUser instanceof ShopUserInterface) {
            $this->addFlashMessage($request, 'error', 'three_brs.ui.social_login.not_logged_in');

            return new RedirectResponse($this->router->generate('sylius_shop_login'));
        }

        $existing = $this->handler->findExistingLinkUser($info);
        if ($existing !== null && $existing->getId() !== $currentUser->getId()) {
            $this->auditLog('link_refused_owned_by_other', $info, $request, ['user_id' => $currentUser->getId()]);
            $this->addFlashMessage($request, 'error', 'three_brs.ui.social_login.already_linked_other_account');

            return new RedirectResponse($this->router->generate('three_brs_shop_social_accounts'));
        }

        if ($existing !== null) {
            $this->addFlashMessage($request, 'info', 'three_brs.ui.social_login.already_linked');

            return new RedirectResponse($this->router->generate('three_brs_shop_social_accounts'));
        }

        $this->handler->linkExistingUser($currentUser, $info);
        $this->auditLog('linked', $info, $request, ['user_id' => $currentUser->getId()]);
        $this->addFlashMessage($request, 'success', 'three_brs.ui.social_login.linked');

        return new RedirectResponse($this->router->generate('three_brs_shop_social_accounts'));
    }

    protected function handleLoginIntent(Request $request, OAuthUserInfoInterface $info): Response
    {
        $existing = $this->handler->findExistingLinkUser($info);
        if ($existing !== null) {
            $this->handler->touchLastUsed($existing, $info);
            $this->authenticate($request, $existing);
            $this->auditLog('login_success', $info, $request, ['user_id' => $existing->getId()]);

            return new RedirectResponse($this->resolveRedirectUrl($request, static::FIREWALL_NAME, $this->router->generate('sylius_shop_account_dashboard')));
        }

        $email = $info->getEmail();
        if ($email === null || $email === '') {
            $this->auditLog('register_refused_missing_email', $info, $request);
            $this->addFlashMessage($request, 'error', 'three_brs.ui.social_login.missing_email');

            return new RedirectResponse($this->router->generate('sylius_shop_login'));
        }

        $userByEmail = $this->handler->findUserByEmail($email);
        if ($userByEmail !== null) {
            $request->getSession()->set(self::CONFIRM_PENDING_SESSION_KEY, [
                'provider' => $info->getProvider(),
                'provider_user_id' => $info->getProviderUserId(),
                'email' => $info->getEmail(),
                'first_name' => $info->getFirstName(),
                'last_name' => $info->getLastName(),
            ]);

            return new RedirectResponse($this->router->generate('three_brs_shop_oauth_confirm_link'));
        }

        if (!$this->handler->canAutoRegister($info)) {
            $this->auditLog('register_refused', $info, $request);
            $this->addFlashMessage($request, 'error', 'three_brs.ui.social_login.auto_register_refused');

            return new RedirectResponse($this->router->generate('sylius_shop_login'));
        }

        $newUser = $this->handler->registerAndLink($info);
        $this->authenticate($request, $newUser);
        $this->auditLog('registered_and_logged_in', $info, $request, ['user_id' => $newUser->getId()]);

        return new RedirectResponse($this->resolveRedirectUrl($request, static::FIREWALL_NAME, $this->router->generate('sylius_shop_account_dashboard')));
    }

    protected function authenticate(Request $request, ShopUserInterface $user): void
    {
        // Session fixation defence: rotate the session ID before binding the
        // newly authenticated token to it, so the pre-authentication session ID
        // (which an attacker could have planted via XSS / set-cookie injection)
        // cannot be reused to ride the resulting authenticated session.
        if ($request->hasSession()) {
            $request->getSession()->migrate(true);
        }

        $token = new PostAuthenticationToken($user, static::FIREWALL_NAME, $user->getRoles());
        $this->tokenStorage->setToken($token);

        if ($request->hasSession()) {
            $request->getSession()->set('_security_' . static::FIREWALL_NAME, serialize($token));
        }

        // Symfony's LoginSuccessEvent is dispatched by the firewall authenticator;
        // OAuth bypasses that machinery and writes the token directly, so the
        // standard session-tracking listener never fires. Invoke the handler
        // directly so OAuth sign-ins land in the Active sessions list and trigger
        // the new-device email like a regular password login.
        $this->sessionLoginHandler->handle($user, $request);
    }

    /** @param array<string, mixed> $extra */
    protected function auditLog(string $event, OAuthUserInfoInterface $info, Request $request, array $extra = []): void
    {
        $this->logger->info(sprintf('three_brs.social_login.shop.%s', $event), array_merge([
            'provider' => $info->getProvider(),
            'provider_user_id' => $info->getProviderUserId(),
            'email' => $info->getEmail(),
            'ip' => $request->getClientIp(),
        ], $extra));
    }
}
