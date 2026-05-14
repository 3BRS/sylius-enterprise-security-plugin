<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
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
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserPasskeyCredentialRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\LastAuthMethodGuardInterface;

class PasskeyDeleteController implements PasskeyDeleteControllerInterface
{
    use FlashHelperTrait;

    public const CSRF_TOKEN_ID = 'three_brs_admin_passkey_delete';

    public function __construct(
        protected EntityManagerInterface $entityManager,
        protected TokenStorageInterface $tokenStorage,
        protected AdminUserPasskeyCredentialRepositoryInterface $credentialRepository,
        protected LastAuthMethodGuardInterface $lastAuthMethodGuard,
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

        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof AdminUserInterface) {
            throw new AccessDeniedHttpException();
        }

        $submittedToken = (string) $request->request->get('_csrf_token', '');
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_TOKEN_ID, $submittedToken))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }

        $credential = $this->credentialRepository->findOneByIdAndAdminUser($id, $user);
        if ($credential === null) {
            throw new NotFoundHttpException();
        }

        if (!$this->lastAuthMethodGuard->canRemovePasskeyForAdminUser($user)) {
            $this->addFlashMessage($request, 'error', 'three_brs.ui.passkey.cannot_remove_last_auth_method');

            return new RedirectResponse($this->router->generate('three_brs_admin_passkey_index'));
        }

        $this->entityManager->remove($credential);
        $this->entityManager->flush();

        $this->addFlashMessage($request, 'success', 'three_brs.ui.passkey.removed');

        return new RedirectResponse($this->router->generate('three_brs_admin_passkey_index'));
    }
}
