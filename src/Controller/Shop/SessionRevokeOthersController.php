<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Shop;

use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\FlashHelperTrait;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\CustomerSessionTrackerInterface;

class SessionRevokeOthersController implements SessionRevokeOthersControllerInterface
{
    use FlashHelperTrait;

    protected const CSRF_TOKEN_ID = 'three_brs_revoke_other_sessions';

    public function __construct(
        protected CustomerSessionTrackerInterface $tracker,
        protected TokenStorageInterface $tokenStorage,
        protected CsrfTokenManagerInterface $csrfTokenManager,
        protected RouterInterface $router,
        protected bool $enabled,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        if (!$this->enabled) {
            throw new NotFoundHttpException();
        }

        $token = (string) $request->request->get('_csrf_token', '');
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_TOKEN_ID, $token))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }

        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof ShopUserInterface) {
            throw new AccessDeniedHttpException();
        }

        if (!$request->hasSession()) {
            throw new BadRequestHttpException('No session.');
        }

        $currentSessionId = $request->getSession()->getId();
        if ($currentSessionId === '') {
            throw new BadRequestHttpException('No session.');
        }

        $this->tracker->revokeOthers($currentSessionId, $user);
        $this->addFlashMessage($request, 'success', 'three_brs.session.others_revoked');

        return new RedirectResponse($this->router->generate(
            'three_brs_shop_sessions',
            ['_locale' => $request->getLocale()],
        ));
    }
}
