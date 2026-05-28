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
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsProviderInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
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
        protected SettingsProviderInterface $settings,
    ) {
        // Recovery-codes toggle and count are read at runtime via the overridden
        // getters below — the constructor parameters on the bundle parent are
        // ignored placeholders so DB-backed setting changes take effect on the
        // next request without requiring a container rebuild.
        parent::__construct(
            $tokenStorage,
            $recoveryGenerator,
            $csrfTokenManager,
            $router,
            false,
            0,
        );
    }

    protected function isRecoveryCodesEnabled(): bool
    {
        return $this->settings->getBool('two_factor_authentication.recovery_codes.enabled', SettingsScope::CUSTOMER);
    }

    protected function getRecoveryCodesCount(): int
    {
        return $this->settings->getInt('two_factor_authentication.recovery_codes.count', SettingsScope::CUSTOMER);
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

    protected function getPlainRecoveryCodesSessionKey(): string
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

    protected function getRecoveryCodesDisplayUrl(): string
    {
        return $this->router->generate('three_brs_shop_two_factor_recovery_codes');
    }
}
