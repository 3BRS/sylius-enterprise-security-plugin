<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Shop;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use ThreeBRS\EnterpriseSecurityBundle\Controller\AbstractTwoFactorRegenerateRecoveryCodesController;
use ThreeBRS\EnterpriseSecurityBundle\TwoFactor\RecoveryCodeGeneratorInterface;
use ThreeBRS\EnterpriseSecurityBundle\TwoFactor\TwoFactorAuthShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerRecoveryCode;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerRecoveryCodeRepositoryInterface;

class TwoFactorRegenerateRecoveryCodesController extends AbstractTwoFactorRegenerateRecoveryCodesController implements TwoFactorRegenerateRecoveryCodesControllerInterface
{
    public const CSRF_TOKEN_ID = 'three_brs_shop_two_factor_regenerate';

    public function __construct(
        protected EntityManagerInterface $entityManager,
        protected CustomerRecoveryCodeRepositoryInterface $recoveryCodeRepository,
        TokenStorageInterface $tokenStorage,
        RecoveryCodeGeneratorInterface $recoveryGenerator,
        CsrfTokenManagerInterface $csrfTokenManager,
        RouterInterface $router,
        bool $recoveryCodesEnabled,
        int $recoveryCodesCount,
    ) {
        parent::__construct(
            $tokenStorage,
            $recoveryGenerator,
            $csrfTokenManager,
            $router,
            $recoveryCodesEnabled,
            $recoveryCodesCount,
        );
    }

    protected function getCsrfTokenId(): string
    {
        return self::CSRF_TOKEN_ID;
    }

    protected function isTwoFactorEnabledUser(UserInterface $user): bool
    {
        return $user instanceof ShopUserInterface &&
            $user instanceof TwoFactorAuthShopUserInterface &&
            $user->isTwoFactorEnabled();
    }

    protected function replaceRecoveryCodesAndCommit(UserInterface $user, array $plainCodes): void
    {
        \assert($user instanceof ShopUserInterface);

        $this->recoveryCodeRepository->deleteAllByShopUser($user);

        foreach ($plainCodes as $plain) {
            $record = new CustomerRecoveryCode();
            $record->setShopUser($user);
            $record->setCodeHash($this->recoveryGenerator->hash($plain));
            $this->entityManager->persist($record);
        }

        $this->entityManager->flush();
    }

    protected function getPlainCodesSessionKey(): string
    {
        return TwoFactorSetupController::SESSION_PLAIN_RECOVERY_CODES;
    }

    protected function getLoginUrl(): string
    {
        return $this->router->generate('sylius_shop_login');
    }

    protected function getDashboardUrl(): string
    {
        return $this->router->generate('sylius_shop_account_dashboard');
    }

    protected function getRecoveryCodesUrl(): string
    {
        return $this->router->generate('three_brs_shop_two_factor_recovery_codes');
    }
}
