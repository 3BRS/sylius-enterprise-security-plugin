<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Shop;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use ThreeBRS\EnterpriseSecurityBundle\Controller\AbstractTwoFactorSetupController;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsProviderInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
use ThreeBRS\EnterpriseSecurityBundle\TwoFactor\QrCodeGeneratorInterface;
use ThreeBRS\EnterpriseSecurityBundle\TwoFactor\RecoveryCodeGeneratorInterface;
use ThreeBRS\EnterpriseSecurityBundle\TwoFactor\TotpSecretGeneratorInterface;
use ThreeBRS\EnterpriseSecurityBundle\TwoFactor\TwoFactorAuthShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerRecoveryCode;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type\TwoFactorVerifyType;
use Twig\Environment;

class TwoFactorSetupController extends AbstractTwoFactorSetupController implements TwoFactorSetupControllerInterface
{
    public const SESSION_PENDING_SECRET = 'three_brs_shop_two_factor_pending_secret';

    public const SESSION_PLAIN_RECOVERY_CODES = 'three_brs_shop_two_factor_plain_recovery_codes';

    public function __construct(
        protected EntityManagerInterface $entityManager,
        protected FormFactoryInterface $formFactory,
        TokenStorageInterface $tokenStorage,
        TotpSecretGeneratorInterface $totpGenerator,
        QrCodeGeneratorInterface $qrGenerator,
        RecoveryCodeGeneratorInterface $recoveryGenerator,
        RouterInterface $router,
        Environment $twig,
        TranslatorInterface $translator,
        CsrfTokenManagerInterface $csrfTokenManager,
        string $issuer,
        protected SettingsProviderInterface $settings,
    ) {
        // Recovery-codes toggle and count are read at runtime via the overridden
        // getters below — the constructor parameters on the bundle parent are
        // ignored placeholders so DB-backed setting changes take effect on the
        // next request without requiring a container rebuild.
        parent::__construct(
            $tokenStorage,
            $totpGenerator,
            $qrGenerator,
            $recoveryGenerator,
            $router,
            $twig,
            $translator,
            $csrfTokenManager,
            $issuer,
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

    protected function isAcceptableUser(UserInterface $user): bool
    {
        return $user instanceof ShopUserInterface && $user instanceof TwoFactorAuthShopUserInterface;
    }

    protected function isTwoFactorAlreadyEnabled(UserInterface $user): bool
    {
        \assert($user instanceof TwoFactorAuthShopUserInterface);

        return $user->isTwoFactorEnabled();
    }

    protected function getUsernameForProvisioning(UserInterface $user): string
    {
        \assert($user instanceof ShopUserInterface);

        return (string) $user->getEmail();
    }

    protected function createVerifyForm(): FormInterface
    {
        return $this->formFactory->create(TwoFactorVerifyType::class);
    }

    protected function enableTwoFactorAndPersistRecoveryCodes(UserInterface $user, string $secret, array $plainCodes): void
    {
        \assert($user instanceof ShopUserInterface && $user instanceof TwoFactorAuthShopUserInterface);

        $user->setTotpSecret($secret);
        $user->setTwoFactorEnabled(true);

        foreach ($plainCodes as $plain) {
            $record = new CustomerRecoveryCode();
            $record->setShopUser($user);
            $record->setCodeHash($this->recoveryGenerator->hash($plain));
            $this->entityManager->persist($record);
        }

        $this->entityManager->flush();
    }

    protected function getLoginUrl(): string
    {
        return $this->router->generate('sylius_shop_login');
    }

    protected function getSetupTemplate(): string
    {
        return '@ThreeBRSSyliusEnterpriseSecurityPlugin/Shop/TwoFactor/setup.html.twig';
    }

    protected function getManageTemplate(): string
    {
        return '@ThreeBRSSyliusEnterpriseSecurityPlugin/Shop/TwoFactor/manage.html.twig';
    }

    protected function getRecoveryCodesDisplayUrl(): string
    {
        return $this->router->generate('three_brs_shop_two_factor_recovery_codes');
    }

    protected function getPendingSecretSessionKey(): string
    {
        return self::SESSION_PENDING_SECRET;
    }

    protected function getPlainRecoveryCodesSessionKey(): string
    {
        return self::SESSION_PLAIN_RECOVERY_CODES;
    }

    protected function getDisableCsrfTokenId(): string
    {
        return TwoFactorDisableController::CSRF_TOKEN_ID;
    }

    protected function getRegenerateCsrfTokenId(): string
    {
        return TwoFactorRegenerateRecoveryCodesController::CSRF_TOKEN_ID;
    }
}
