<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use ThreeBRS\EnterpriseSecurityBundle\TwoFactor\RecoveryCodeGeneratorInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserRecoveryCode;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\TwoFactorAuthAdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserRecoveryCodeRepositoryInterface;

class TwoFactorRegenerateRecoveryCodesController implements TwoFactorRegenerateRecoveryCodesControllerInterface
{
    public const CSRF_TOKEN_ID = 'three_brs_admin_two_factor_regenerate';

    public function __construct(
        protected TokenStorageInterface $tokenStorage,
        protected EntityManagerInterface $entityManager,
        protected AdminUserRecoveryCodeRepositoryInterface $recoveryCodeRepository,
        protected RecoveryCodeGeneratorInterface $recoveryGenerator,
        protected CsrfTokenManagerInterface $csrfTokenManager,
        protected RouterInterface $router,
        protected bool $recoveryCodesEnabled,
        protected int $recoveryCodesCount,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof AdminUserInterface || !$user instanceof TwoFactorAuthAdminUserInterface) {
            return new RedirectResponse($this->router->generate('sylius_admin_login'));
        }

        if (!$user->isTwoFactorEnabled() || !$this->recoveryCodesEnabled) {
            return new RedirectResponse($this->router->generate('sylius_admin_dashboard'));
        }

        $token = (string) $request->request->get('_csrf_token', '');
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_TOKEN_ID, $token))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }

        $this->recoveryCodeRepository->deleteAllByAdminUser($user);

        $plainCodes = $this->recoveryGenerator->generate($this->recoveryCodesCount);
        foreach ($plainCodes as $plain) {
            $record = new AdminUserRecoveryCode();
            $record->setAdminUser($user);
            $record->setCodeHash($this->recoveryGenerator->hash($plain));
            $this->entityManager->persist($record);
        }

        $this->entityManager->flush();

        $request->getSession()->set(TwoFactorSetupController::SESSION_PLAIN_RECOVERY_CODES, $plainCodes);

        return new RedirectResponse($this->router->generate('three_brs_admin_two_factor_recovery_codes'));
    }
}
