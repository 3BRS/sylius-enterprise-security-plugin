<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use ThreeBRS\EnterpriseSecurityBundle\Controller\AbstractTwoFactorRegenerateRecoveryCodesController;
use ThreeBRS\EnterpriseSecurityBundle\RateLimit\RateLimitGuardInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsProviderInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
use ThreeBRS\EnterpriseSecurityBundle\TwoFactor\RecoveryCodeGeneratorInterface;
use ThreeBRS\EnterpriseSecurityBundle\TwoFactor\TwoFactorAuthAdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\TwoFactorPageTargetPathTrait;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserRecoveryCode;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserRecoveryCodeRepositoryInterface;

class TwoFactorRegenerateRecoveryCodesController extends AbstractTwoFactorRegenerateRecoveryCodesController implements TwoFactorRegenerateRecoveryCodesControllerInterface
{
    use TwoFactorPageTargetPathTrait;

    public const CSRF_TOKEN_ID = 'three_brs_admin_two_factor_regenerate';

    public function __construct(
        protected EntityManagerInterface $entityManager,
        protected AdminUserRecoveryCodeRepositoryInterface $recoveryCodeRepository,
        TokenStorageInterface $tokenStorage,
        RecoveryCodeGeneratorInterface $recoveryGenerator,
        CsrfTokenManagerInterface $csrfTokenManager,
        RouterInterface $router,
        protected SettingsProviderInterface $settings,
        ?TotpAuthenticatorInterface $totpAuthenticator,
        RateLimitGuardInterface $rateLimitGuard,
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
            $totpAuthenticator,
            $rateLimitGuard,
        );
    }

    protected function isRecoveryCodesEnabled(): bool
    {
        return $this->settings->getBool('two_factor_authentication.recovery_codes.enabled', SettingsScope::ADMIN);
    }

    protected function getRecoveryCodesCount(): int
    {
        return $this->settings->getInt('two_factor_authentication.recovery_codes.count', SettingsScope::ADMIN);
    }

    protected function getCsrfTokenId(): string
    {
        return self::CSRF_TOKEN_ID;
    }

    protected function isTwoFactorEnabledUser(UserInterface $user): bool
    {
        return $user instanceof AdminUserInterface &&
            $user instanceof TwoFactorAuthAdminUserInterface &&
            $user->isTwoFactorEnabled();
    }

    protected function replaceRecoveryCodesAndCommit(UserInterface $user, array $plainCodes): void
    {
        \assert($user instanceof AdminUserInterface);

        $this->recoveryCodeRepository->deleteAllByAdminUser($user);

        foreach ($plainCodes as $plain) {
            $record = new AdminUserRecoveryCode();
            $record->setAdminUser($user);
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
        return $this->router->generate('sylius_admin_login');
    }

    protected function getDashboardUrl(): string
    {
        return $this->router->generate('sylius_admin_dashboard');
    }

    protected function getRecoveryCodesDisplayUrl(): string
    {
        return $this->router->generate('three_brs_admin_two_factor_recovery_codes');
    }

    protected function verifyRecoveryCode(UserInterface $user, string $code): bool
    {
        \assert($user instanceof AdminUserInterface);

        return $this->recoveryCodeRepository->findUnusedByAdminUserAndHash($user, $this->recoveryGenerator->hash($code)) !== null;
    }

    protected function getRateLimitGroup(): string
    {
        return 'admin';
    }

    protected function getRedirectAfterRefusedCodeUrl(): string
    {
        return $this->getTwoFactorPageUrl();
    }

    protected function getFirewallName(): string
    {
        return 'admin';
    }

    protected function getTwoFactorPageUrl(): string
    {
        return $this->router->generate('three_brs_admin_two_factor_setup');
    }
}
