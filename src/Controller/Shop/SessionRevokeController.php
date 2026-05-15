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
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerSessionRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\CustomerSessionTrackerInterface;

class SessionRevokeController implements SessionRevokeControllerInterface
{
    use FlashHelperTrait;

    protected const CSRF_TOKEN_ID = 'three_brs_revoke_session';

    public function __construct(
        protected CustomerSessionRepositoryInterface $repository,
        protected CustomerSessionTrackerInterface $tracker,
        protected TokenStorageInterface $tokenStorage,
        protected CsrfTokenManagerInterface $csrfTokenManager,
        protected RouterInterface $router,
        protected bool $enabled,
    ) {
    }

    public function __invoke(Request $request, int $id): Response
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

        $session = $this->repository->findActiveByIdForShopUser($id, $user);
        if ($session === null) {
            throw new NotFoundHttpException();
        }

        $currentSessionId = $request->hasSession() ? $request->getSession()->getId() : '';
        if ($currentSessionId !== '' && $session->getSessionId() === $currentSessionId) {
            $this->addFlashMessage($request, 'error', 'three_brs.session.cannot_revoke_current');

            return new RedirectResponse($this->router->generate(
                'three_brs_shop_sessions',
                ['_locale' => $request->getLocale()],
            ));
        }

        $this->tracker->revoke($session);
        $this->addFlashMessage($request, 'success', 'three_brs.session.revoked');

        return new RedirectResponse($this->router->generate(
            'three_brs_shop_sessions',
            ['_locale' => $request->getLocale()],
        ));
    }
}
