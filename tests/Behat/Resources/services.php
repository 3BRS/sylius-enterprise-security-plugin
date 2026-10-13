<?php

declare(strict_types=1);

use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Hook\RateLimiterCacheHookContext;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Hook\SecuritySettingsHookContext;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Admin\AccountDeletionContext as AdminAccountDeletionContext;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Admin\AccountSecurityChangesContext as AdminAccountSecurityChangesContext;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Admin\CustomerManagementContext;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Admin\CustomerPasswordPolicyContext;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Admin\IpBlacklistContext;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Admin\IpWhitelistContext;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Admin\LockoutContext as AdminLockoutContext;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Admin\MagicLinkContext as AdminMagicLinkContext;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Admin\PasskeyCeremonyContext as AdminPasskeyCeremonyContext;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Admin\PasskeyContext as AdminPasskeyContext;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Admin\PasswordChangeNotificationContext as AdminPasswordChangeNotificationContext;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Admin\PasswordExpirationContext as AdminPasswordExpirationContext;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Admin\PasswordHistoryContext as AdminPasswordHistoryContext;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Admin\PasswordLoginContext as AdminPasswordLoginContext;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Admin\PasswordPolicyContext as AdminPasswordPolicyContext;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Admin\PendingSignInContext as AdminPendingSignInContext;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Admin\RateLimitContext as AdminRateLimitContext;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Admin\SecuritySettingsContext;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Admin\SessionManagementContext as AdminSessionManagementContext;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Admin\SocialLoginContext as AdminSocialLoginContext;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Admin\TwoFactorEnforcementContext as AdminTwoFactorEnforcementContext;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Admin\TwoFactorLoginContext as AdminTwoFactorLoginContext;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Admin\TwoFactorRecoveryContext as AdminTwoFactorRecoveryContext;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Admin\TwoFactorSetupContext as AdminTwoFactorSetupContext;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Admin\TwoFactorTrustedDeviceContext as AdminTwoFactorTrustedDeviceContext;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Shop\AccountDeletionContext as ShopAccountDeletionContext;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Shop\AccountSecurityChangesContext as ShopAccountSecurityChangesContext;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Shop\AlternativeSignInContext;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Shop\ChangePasswordPolicyContext;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Shop\GuestCartContext;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Shop\LockoutContext as ShopLockoutContext;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Shop\MagicLinkContext as ShopMagicLinkContext;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Shop\PasskeyBrowserContext;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Shop\PasskeyCeremonyContext as ShopPasskeyCeremonyContext;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Shop\PasskeyContext as ShopPasskeyContext;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Shop\PasswordChangeNotificationContext as ShopPasswordChangeNotificationContext;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Shop\PasswordExpirationContext as ShopPasswordExpirationContext;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Shop\PasswordHistoryContext as ShopPasswordHistoryContext;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Shop\PasswordLoginContext as ShopPasswordLoginContext;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Shop\PasswordPolicyContext as ShopPasswordPolicyContext;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Shop\PendingSignInContext as ShopPendingSignInContext;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Shop\RateLimitContext as ShopRateLimitContext;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Shop\SessionManagementContext as ShopSessionManagementContext;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Shop\SetPasswordContext;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Shop\SocialLoginContext as ShopSocialLoginContext;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Shop\TwoFactorEnforcementContext as ShopTwoFactorEnforcementContext;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Shop\TwoFactorLoginContext as ShopTwoFactorLoginContext;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Shop\TwoFactorRecoveryContext as ShopTwoFactorRecoveryContext;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Shop\TwoFactorSetupContext as ShopTwoFactorSetupContext;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Shop\TwoFactorTrustedDeviceContext as ShopTwoFactorTrustedDeviceContext;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Service\Passkey\FakeAuthenticator;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\OAuth\FakeOAuthState;
use ThreeBRS\EnterpriseSecurityBundle\MagicLink\MagicLinkTokenGenerator;
use ThreeBRS\EnterpriseSecurityBundle\TwoFactor\RecoveryCodeGenerator;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserIpWhitelistRepository;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserPasskeyCredentialRepository;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserRecoveryCodeRepository;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserSessionRepository;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserSocialAccountLinkRepository;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerDeletionRequestRepository;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerPasskeyCredentialRepository;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerRecoveryCodeRepository;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerSessionRepository;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerSocialAccountLinkRepository;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\AccountDeletion\CustomerDueDeletionsProcessor;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Lockout\AdminUserLockoutManager;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Lockout\ShopUserLockoutManager;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\CustomerSessionTracker;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings\SettingsProvider;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings\SettingsWriter;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->public();

    $services->set('three_brs.behat.context.ui.shop.password_policy', ShopPasswordPolicyContext::class)
        ->args([service('behat.mink.default_session')]);

    $services->set('three_brs.behat.context.ui.admin.password_policy', AdminPasswordPolicyContext::class)
        ->args([service('behat.mink.default_session')]);

    $services->set('three_brs.behat.context.ui.shop.change_password_policy', ChangePasswordPolicyContext::class)
        ->args([service('behat.mink.default_session')]);

    $services->set('three_brs.behat.context.ui.admin.customer_password_policy', CustomerPasswordPolicyContext::class)
        ->args([
            service('behat.mink.default_session'),
            service('sylius.repository.customer'),
            service('sylius.email_sender'),
        ]);

    $services->set('three_brs.behat.context.ui.shop.password_history', ShopPasswordHistoryContext::class)
        ->args([
            service('behat.mink.default_session'),
            service('sylius.repository.customer'),
            service('doctrine.orm.entity_manager'),
        ]);

    $services->set('three_brs.behat.context.ui.shop.password_expiration', ShopPasswordExpirationContext::class)
        ->args([
            service('behat.mink.default_session'),
            service('sylius.repository.customer'),
            service('doctrine.orm.entity_manager'),
        ]);

    $services->set('three_brs.behat.context.ui.admin.password_history', AdminPasswordHistoryContext::class)
        ->args([
            service('behat.mink.default_session'),
            service('sylius.repository.admin_user'),
            service('doctrine.orm.entity_manager'),
        ]);

    $services->set('three_brs.behat.context.ui.admin.password_expiration', AdminPasswordExpirationContext::class)
        ->args([
            service('behat.mink.default_session'),
            service('sylius.repository.admin_user'),
            service('doctrine.orm.entity_manager'),
            service('router'),
            service('sylius.behat.shared_storage'),
        ]);

    $services->set('three_brs.behat.context.ui.shop.password_change_notification', ShopPasswordChangeNotificationContext::class)
        ->args([
            service('sylius.email_sender'),
            service('sylius.repository.customer'),
            service('sylius.security.password_updater'),
            service('doctrine.orm.entity_manager'),
            service('behat.mink.default_session'),
            service('router'),
            service('sylius.repository.shop_user'),
            service('doctrine.orm.entity_manager'),
        ]);

    $services->set('three_brs.behat.context.ui.admin.password_change_notification', AdminPasswordChangeNotificationContext::class)
        ->args([
            service('sylius.email_sender'),
            service('sylius.repository.admin_user'),
            service('sylius.security.password_updater'),
            service('doctrine.orm.entity_manager'),
            service('behat.mink.default_session'),
            service('router'),
        ]);

    $services->set('three_brs.behat.context.ui.shop.two_factor_setup', ShopTwoFactorSetupContext::class)
        ->args([
            service('behat.mink.default_session'),
            service('sylius.repository.customer'),
            service('doctrine.orm.entity_manager'),
            service(CustomerRecoveryCodeRepository::class),
            service(RecoveryCodeGenerator::class),
            service('translator'),
        ]);

    $services->set('three_brs.behat.context.ui.shop.two_factor_login', ShopTwoFactorLoginContext::class)
        ->args([
            service('behat.mink.default_session'),
            service('sylius.repository.customer'),
            service('doctrine.orm.entity_manager'),
        ]);

    $services->set('three_brs.behat.context.ui.admin.two_factor_setup', AdminTwoFactorSetupContext::class)
        ->args([
            service('behat.mink.default_session'),
            service('sylius.repository.admin_user'),
            service('doctrine.orm.entity_manager'),
            service(AdminUserRecoveryCodeRepository::class),
            service(RecoveryCodeGenerator::class),
            service('translator'),
        ]);

    $services->set('three_brs.behat.context.ui.admin.two_factor_login', AdminTwoFactorLoginContext::class)
        ->args([
            service('behat.mink.default_session'),
            service('sylius.repository.admin_user'),
            service('doctrine.orm.entity_manager'),
        ]);

    $services->set('three_brs.behat.context.ui.shop.two_factor_recovery', ShopTwoFactorRecoveryContext::class)
        ->args([
            service('behat.mink.default_session'),
            service('sylius.repository.customer'),
            service('doctrine.orm.entity_manager'),
            service(RecoveryCodeGenerator::class),
            service(CustomerRecoveryCodeRepository::class),
        ]);

    $services->set('three_brs.behat.context.ui.admin.two_factor_recovery', AdminTwoFactorRecoveryContext::class)
        ->args([
            service('behat.mink.default_session'),
            service('sylius.repository.admin_user'),
            service('doctrine.orm.entity_manager'),
            service(RecoveryCodeGenerator::class),
            service(AdminUserRecoveryCodeRepository::class),
        ]);

    $services->set('three_brs.behat.context.ui.shop.two_factor_trusted_device', ShopTwoFactorTrustedDeviceContext::class)
        ->args([
            service('behat.mink.default_session'),
            service('sylius.repository.customer'),
            service('doctrine.orm.entity_manager'),
            service(CustomerSessionRepository::class),
            service(CustomerSessionTracker::class),
        ]);

    $services->set('three_brs.behat.context.ui.admin.two_factor_trusted_device', AdminTwoFactorTrustedDeviceContext::class)
        ->args([
            service('behat.mink.default_session'),
            service('sylius.repository.admin_user'),
            service('doctrine.orm.entity_manager'),
        ]);

    $services->set('three_brs.behat.context.ui.shop.two_factor_enforcement', ShopTwoFactorEnforcementContext::class)
        ->args([
            service('behat.mink.default_session'),
            service(SettingsWriter::class),
            service(SettingsProvider::class),
        ]);

    $services->set('three_brs.behat.context.ui.admin.two_factor_enforcement', AdminTwoFactorEnforcementContext::class)
        ->args([
            service('behat.mink.default_session'),
            service(SettingsWriter::class),
            service(SettingsProvider::class),
            service('translator'),
        ]);

    $services->set('three_brs.behat.context.ui.shop.social_login', ShopSocialLoginContext::class)
        ->args([
            service('behat.mink.default_session'),
            service('sylius.repository.customer'),
            service(CustomerSocialAccountLinkRepository::class),
            service('doctrine.orm.entity_manager'),
            service(FakeOAuthState::class),
            service('sylius.email_sender'),
            service('translator'),
        ]);

    $services->set('three_brs.behat.context.ui.admin.social_login', AdminSocialLoginContext::class)
        ->args([
            service('behat.mink.default_session'),
            service('sylius.repository.admin_user'),
            service(AdminUserSocialAccountLinkRepository::class),
            service('doctrine.orm.entity_manager'),
            service(FakeOAuthState::class),
            service('sylius.email_sender'),
        ]);

    $services->set('three_brs.behat.context.ui.shop.magic_link', ShopMagicLinkContext::class)
        ->args([
            service('behat.mink.default_session'),
            service('sylius.repository.customer'),
            service(MagicLinkTokenGenerator::class),
            service('doctrine.orm.entity_manager'),
            service('sylius.email_sender'),
        ]);

    $services->set('three_brs.behat.context.ui.admin.magic_link', AdminMagicLinkContext::class)
        ->args([
            service('behat.mink.default_session'),
            service('sylius.repository.admin_user'),
            service(MagicLinkTokenGenerator::class),
            service('doctrine.orm.entity_manager'),
            service('sylius.email_sender'),
            service(SettingsWriter::class),
            service(SettingsProvider::class),
        ]);

    $services->set('three_brs.behat.context.ui.shop.passkey', ShopPasskeyContext::class)
        ->args([
            service('behat.mink.default_session'),
            service('sylius.repository.customer'),
            service(CustomerPasskeyCredentialRepository::class),
            service('doctrine.orm.entity_manager'),
            service('router'),
        ]);

    $services->set('three_brs.behat.context.ui.admin.passkey', AdminPasskeyContext::class)
        ->args([
            service('behat.mink.default_session'),
            service('sylius.repository.admin_user'),
            service(AdminUserPasskeyCredentialRepository::class),
            service('doctrine.orm.entity_manager'),
            service('router'),
        ]);

    $services->set(FakeAuthenticator::class, FakeAuthenticator::class)
        ->public();

    $services->set('three_brs.behat.context.ui.shop.alternative_sign_in', AlternativeSignInContext::class)
        ->args([
            service('behat.mink.default_session'),
            service('router'),
            service('sylius.repository.customer'),
            service(ShopUserLockoutManager::class),
        ]);

    $services->set('three_brs.behat.context.ui.shop.passkey_ceremony', ShopPasskeyCeremonyContext::class)
        ->args([
            service(AbstractBrowser::class),
            service('router'),
            service('doctrine.orm.entity_manager'),
            service('sylius.repository.customer'),
            service(CustomerPasskeyCredentialRepository::class),
            service(FakeAuthenticator::class),
        ]);

    $services->set('three_brs.behat.context.ui.admin.passkey_ceremony', AdminPasskeyCeremonyContext::class)
        ->args([
            service(AbstractBrowser::class),
            service('router'),
            service('doctrine.orm.entity_manager'),
            service('sylius.repository.admin_user'),
            service(AdminUserPasskeyCredentialRepository::class),
            service(FakeAuthenticator::class),
        ]);

    $services->set('three_brs.behat.context.ui.admin.password_login', AdminPasswordLoginContext::class)
        ->args([
            service('behat.mink.default_session'),
            service(SettingsWriter::class),
            service(SettingsProvider::class),
            service('router'),
            service('sylius.repository.customer'),
            service('sylius.repository.admin_user'),
            service('sylius.behat.shared_storage'),
            service('doctrine.orm.entity_manager'),
        ]);

    $services->set('three_brs.behat.context.ui.shop.password_login', ShopPasswordLoginContext::class)
        ->args([
            service('behat.mink.default_session'),
            service(SettingsWriter::class),
            service(SettingsProvider::class),
            service('router'),
            service('sylius.behat.shared_storage'),
        ]);

    $services->set('three_brs.behat.context.ui.shop.lockout', ShopLockoutContext::class)
        ->args([
            service('behat.mink.default_session'),
            service('sylius.repository.customer'),
            service('doctrine.orm.entity_manager'),
            service('router'),
            service(ShopUserLockoutManager::class),
            service('sylius.behat.shared_storage'),
            service('three_brs.rate_limiter.cache_pool'),
            service(SettingsWriter::class),
            service(SettingsProvider::class),
        ]);

    $services->set('three_brs.behat.context.ui.admin.lockout', AdminLockoutContext::class)
        ->args([
            service('behat.mink.default_session'),
            service('sylius.repository.admin_user'),
            service('sylius.repository.customer'),
            service('doctrine.orm.entity_manager'),
            service('router'),
            service(AdminUserLockoutManager::class),
            service('sylius.behat.shared_storage'),
            service('three_brs.rate_limiter.cache_pool'),
        ]);

    $services->set('three_brs.behat.context.ui.shop.rate_limit', ShopRateLimitContext::class)
        ->args([
            service('behat.mink.default_session'),
            service('sylius.repository.customer'),
            service('doctrine.orm.entity_manager'),
            service('router'),
        ]);

    $services->set('three_brs.behat.context.ui.admin.rate_limit', AdminRateLimitContext::class)
        ->args([
            service('behat.mink.default_session'),
            service('sylius.repository.admin_user'),
            service('doctrine.orm.entity_manager'),
            service('router'),
        ]);

    $services->set('three_brs.behat.context.ui.shop.passkey_browser', PasskeyBrowserContext::class)
        ->args([
            service('security.user_password_hasher'),
            service('sylius.repository.shop_user'),
            service('doctrine.orm.entity_manager'),
            service('translator'),
        ]);

    $services->set('three_brs.behat.context.hook.rate_limiter_cache', RateLimiterCacheHookContext::class)
        ->args([service('three_brs.rate_limiter.cache_pool')]);

    $services->set('three_brs.behat.context.hook.security_settings', SecuritySettingsHookContext::class)
        ->args([
            service('doctrine.orm.entity_manager'),
            service(SettingsProvider::class),
        ]);

    $services->set('three_brs.behat.context.ui.shop.session_management', ShopSessionManagementContext::class)
        ->args([
            service('behat.mink.default_session'),
            service('sylius.repository.customer'),
            service(CustomerSessionRepository::class),
            service('doctrine.orm.entity_manager'),
            service('router'),
            service('sylius.email_sender'),
            service('sylius.behat.shared_storage'),
        ]);

    $services->set('three_brs.behat.context.ui.admin.session_management', AdminSessionManagementContext::class)
        ->args([
            service('behat.mink.default_session'),
            service('sylius.repository.admin_user'),
            service(AdminUserSessionRepository::class),
            service('doctrine.orm.entity_manager'),
            service('router'),
            service('sylius.email_sender'),
            service('sylius.behat.shared_storage'),
        ]);

    $services->set('three_brs.behat.context.ui.admin.security_settings', SecuritySettingsContext::class)
        ->args([
            service('behat.mink.default_session'),
            service('router'),
            service(SettingsProvider::class),
            service(SettingsWriter::class),
            service('doctrine.orm.entity_manager'),
        ]);

    $services->set('three_brs.behat.context.ui.shop.account_deletion', ShopAccountDeletionContext::class)
        ->args([
            service('behat.mink.default_session'),
            service('router'),
            service('sylius.repository.customer'),
            service(CustomerDeletionRequestRepository::class),
            service(CustomerDueDeletionsProcessor::class),
            service('doctrine.orm.entity_manager'),
            service('sylius.behat.shared_storage'),
            service('sylius.email_sender'),
            service(CustomerSessionRepository::class),
        ]);

    $services->set('three_brs.behat.context.ui.shop.set_password', SetPasswordContext::class)
        ->args([
            service('behat.mink.default_session'),
            service('router'),
            service('sylius.fixture.example_factory.shop_user'),
            service('sylius.repository.shop_user'),
            service('doctrine.orm.entity_manager'),
            service('security.user_password_hasher'),
        ]);

    $services->set('three_brs.behat.context.ui.admin.account_deletion', AdminAccountDeletionContext::class)
        ->args([
            service('behat.mink.default_session'),
            service('router'),
            service('sylius.repository.customer'),
            service(CustomerDeletionRequestRepository::class),
            service('doctrine.orm.entity_manager'),
        ]);

    $services->set('three_brs.behat.context.ui.admin.ip_whitelist', IpWhitelistContext::class)
        ->args([
            service('behat.mink.default_session'),
            service('router'),
            service('sylius.repository.admin_user'),
            service(AdminUserIpWhitelistRepository::class),
            service(SettingsWriter::class),
            service('doctrine.orm.entity_manager'),
        ]);

    $services->set('three_brs.behat.context.ui.admin.ip_blacklist', IpBlacklistContext::class)
        ->args([service(SettingsWriter::class)]);

    $services->set('three_brs.behat.context.ui.admin.customer_management', CustomerManagementContext::class)
        ->args([
            service('behat.mink.default_session'),
            service('router'),
            service('sylius.repository.customer'),
            service(CustomerSessionRepository::class),
            service('doctrine.orm.entity_manager'),
        ]);

    $services->set('three_brs.behat.context.ui.shop.account_security_changes', ShopAccountSecurityChangesContext::class)
        ->args([
            service('behat.mink.default_session'),
            service('router'),
            service('sylius.behat.shared_storage'),
        ]);

    $services->set('three_brs.behat.context.ui.admin.account_security_changes', AdminAccountSecurityChangesContext::class)
        ->args([
            service('behat.mink.default_session'),
            service('router'),
            service('sylius.behat.shared_storage'),
        ]);

    $services->set('three_brs.behat.context.ui.shop.pending_sign_in', ShopPendingSignInContext::class)
        ->args([
            service('behat.mink.default_session'),
            service('router'),
        ]);

    $services->set('three_brs.behat.context.ui.admin.pending_sign_in', AdminPendingSignInContext::class)
        ->args([
            service('behat.mink.default_session'),
            service('router'),
        ]);

    $services->set('three_brs.behat.context.ui.shop.guest_cart', GuestCartContext::class)
        ->args([
            service('sylius.repository.order'),
            service('sylius.repository.customer'),
            service('doctrine.orm.entity_manager'),
            service('sylius.behat.shared_storage'),
            service('sylius.factory.order'),
            service('sylius.factory.order_item'),
            service('sylius.modifier.order_item_quantity'),
            service('sylius.order_processing.order_processor'),
            service('session.factory'),
            service('behat.mink.default_session'),
            service('sylius.repository.channel'),
            service('sylius.repository.product_variant'),
        ]);
};
