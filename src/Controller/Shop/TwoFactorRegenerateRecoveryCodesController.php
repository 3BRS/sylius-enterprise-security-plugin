<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Shop;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerRecoveryCode;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\TwoFactorAuthShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerRecoveryCodeRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\RecoveryCodeGeneratorInterface;

class TwoFactorRegenerateRecoveryCodesController implements TwoFactorRegenerateRecoveryCodesControllerInterface
{
    public const CSRF_TOKEN_ID = 'three_brs_shop_two_factor_regenerate';

    public function __construct(
        protected TokenStorageInterface $tokenStorage,
        protected EntityManagerInterface $entityManager,
        protected CustomerRecoveryCodeRepositoryInterface $recoveryCodeRepository,
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
        if (!$user instanceof ShopUserInterface || !$user instanceof TwoFactorAuthShopUserInterface) {
            return new RedirectResponse($this->router->generate('sylius_shop_login'));
        }

        if (!$user->isTwoFactorEnabled() || !$this->recoveryCodesEnabled) {
            return new RedirectResponse($this->router->generate('sylius_shop_account_dashboard'));
        }

        $token = (string) $request->request->get('_csrf_token', '');
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_TOKEN_ID, $token))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }

        $this->recoveryCodeRepository->deleteAllByShopUser($user);

        $plainCodes = $this->recoveryGenerator->generate($this->recoveryCodesCount);
        foreach ($plainCodes as $plain) {
            $record = new CustomerRecoveryCode();
            $record->setShopUser($user);
            $record->setCodeHash($this->recoveryGenerator->hash($plain));
            $this->entityManager->persist($record);
        }

        $this->entityManager->flush();

        $request->getSession()->set(TwoFactorSetupController::SESSION_PLAIN_RECOVERY_CODES, $plainCodes);

        return new RedirectResponse($this->router->generate('three_brs_shop_two_factor_recovery_codes'));
    }
}
