<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\TwoFactorAuthAdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserRecoveryCodeRepositoryInterface;

class TwoFactorDisableController implements TwoFactorDisableControllerInterface
{
    public const CSRF_TOKEN_ID = 'three_brs_admin_two_factor_disable';

    public function __construct(
        protected TokenStorageInterface $tokenStorage,
        protected EntityManagerInterface $entityManager,
        protected AdminUserRecoveryCodeRepositoryInterface $recoveryCodeRepository,
        protected CsrfTokenManagerInterface $csrfTokenManager,
        protected RouterInterface $router,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof AdminUserInterface || !$user instanceof TwoFactorAuthAdminUserInterface) {
            return new RedirectResponse($this->router->generate('sylius_admin_login'));
        }

        $token = (string) $request->request->get('_csrf_token', '');
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_TOKEN_ID, $token))) {
            throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Invalid CSRF token.');
        }

        $user->setTotpSecret(null);
        $user->setTwoFactorEnabled(false);
        $user->bumpTrustedTokenVersion();
        $this->recoveryCodeRepository->deleteAllByAdminUser($user);
        $this->entityManager->flush();

        $session = $request->getSession();
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add('success', 'three_brs.two_factor.disabled');
        }

        return new RedirectResponse($this->router->generate('sylius_admin_dashboard'));
    }
}
