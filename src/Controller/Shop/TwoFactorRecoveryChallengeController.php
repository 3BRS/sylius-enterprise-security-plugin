<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Shop;

use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Scheb\TwoFactorBundle\Security\Http\Authenticator\TwoFactorAuthenticator;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\RecoveryCodeVerifierInterface;
use Twig\Environment;

class TwoFactorRecoveryChallengeController implements TwoFactorRecoveryChallengeControllerInterface
{
    use TargetPathTrait;

    protected const FIREWALL_NAME = 'shop';

    public function __construct(
        protected TokenStorageInterface $tokenStorage,
        protected RecoveryCodeVerifierInterface $recoveryCodeVerifier,
        protected RouterInterface $router,
        protected Environment $twig,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $token = $this->tokenStorage->getToken();
        if (!$token instanceof TwoFactorTokenInterface) {
            throw new AccessDeniedException('User is not in a two-factor authentication process.');
        }

        $user = $token->getUser();
        if (!$user instanceof ShopUserInterface) {
            throw new AccessDeniedException('Invalid user.');
        }

        $error = null;
        if ($request->isMethod('POST')) {
            $code = trim((string) $request->request->get('_recovery_code'));
            if ($code === '') {
                $error = 'three_brs.ui.two_factor.recovery_code_required';
            } elseif (!$this->recoveryCodeVerifier->verifyAndConsumeForShopUser($user, $code)) {
                $error = 'three_brs.ui.two_factor.invalid_recovery_code';
            } else {
                $authenticatedToken = $token->getAuthenticatedToken();
                $authenticatedToken->setAttribute(TwoFactorAuthenticator::FLAG_2FA_COMPLETE, true);
                $this->tokenStorage->setToken($authenticatedToken);

                return new RedirectResponse($this->resolveRedirectUrl($request));
            }
        }

        return new Response($this->twig->render(
            '@ThreeBRSSyliusEnterpriseSecurityPlugin/Shop/TwoFactor/recovery_challenge.html.twig',
            ['error' => $error],
        ));
    }

    protected function resolveRedirectUrl(Request $request): string
    {
        if ($request->hasSession()) {
            $targetPath = $this->getTargetPath($request->getSession(), static::FIREWALL_NAME);
            if (is_string($targetPath) && $targetPath !== '') {
                return $targetPath;
            }
        }

        return $this->router->generate('sylius_shop_homepage');
    }
}
