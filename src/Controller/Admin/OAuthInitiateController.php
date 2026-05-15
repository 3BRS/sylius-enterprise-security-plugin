<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use ThreeBRS\EnterpriseSecurityBundle\OAuth\Exception\OAuthProviderException;
use ThreeBRS\EnterpriseSecurityBundle\OAuth\OAuthProviderRegistryInterface;

class OAuthInitiateController implements OAuthInitiateControllerInterface
{
    public const STATE_SESSION_KEY = 'three_brs_oauth_state_admin';

    public const INTENT_SESSION_KEY = 'three_brs_oauth_intent_admin';

    public function __construct(
        protected OAuthProviderRegistryInterface $registry,
        protected RouterInterface $router,
    ) {
    }

    public function __invoke(Request $request, string $provider): Response
    {
        if (!$this->registry->has($provider)) {
            throw new OAuthProviderException(sprintf('Unknown OAuth provider "%s".', $provider));
        }

        $oauth = $this->registry->get($provider);
        if (!$oauth->isEnabledForAdmin()) {
            throw new OAuthProviderException(sprintf('OAuth provider "%s" is disabled for admin.', $provider));
        }

        $state = bin2hex(random_bytes(16));
        $session = $request->getSession();
        $session->set(self::STATE_SESSION_KEY . '_' . $provider, $state);

        $intent = $request->query->getString('intent', 'login');
        if (!in_array($intent, ['login', 'link'], true)) {
            $intent = 'login';
        }
        $session->set(self::INTENT_SESSION_KEY, $intent);

        $redirectUri = $this->router->generate(
            'three_brs_admin_oauth_callback',
            ['provider' => $provider],
            RouterInterface::ABSOLUTE_URL,
        );

        $url = $oauth->getAuthorizationUrl($redirectUri, $state, 'admin');

        return new RedirectResponse($url);
    }
}
