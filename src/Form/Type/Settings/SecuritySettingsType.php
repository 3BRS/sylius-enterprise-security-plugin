<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type\Settings;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\PasswordLoginCheckerInterface;

/**
 * @extends AbstractType<array<string, mixed>>
 */
class SecuritySettingsType extends AbstractType implements SecuritySettingsTypeInterface
{
    public function __construct(
        protected PasswordLoginCheckerInterface $passwordLoginChecker,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var SettingsScope $scope */
        $scope = $options['scope'];

        // With password login disabled for the scope, the whole password-management family
        // (policy, history, expiration, change notification) is irrelevant — render it
        // disabled. Stored values are preserved; they just become editable again once
        // password login is turned back on.
        $passwordSettingsDisabled = !$this->passwordLoginChecker->isEnabled($scope);

        $builder
            ->add('password_policy', PasswordPolicySettingsType::class, [
                'disabled' => $passwordSettingsDisabled,
            ])
            ->add('password_history', PasswordHistorySettingsType::class, [
                'disabled' => $passwordSettingsDisabled,
            ])
            ->add('password_expiration', PasswordExpirationSettingsType::class, [
                'disabled' => $passwordSettingsDisabled,
            ])
            ->add('password_change_notification', SimpleToggleSettingsType::class, [
                'label' => 'three_brs.ui.security_settings.password_change_notification.enabled',
                'disabled' => $passwordSettingsDisabled,
            ])
            ->add('two_factor_authentication', TwoFactorSettingsType::class)
            ->add('magic_link', MagicLinkSettingsType::class)
            ->add('passkey', PasskeySettingsType::class)
            ->add('account_lockout', AccountLockoutSettingsType::class)
        ;

        $rateLimitActions = $scope === SettingsScope::CUSTOMER
            ? ['login', 'password_reset', 'register', 'magic_link']
            : ['login', 'password_reset', 'magic_link'];
        $builder->add('rate_limit', RateLimitSettingsType::class, [
            'actions' => $rateLimitActions,
        ]);

        $builder
            ->add('session_management', SimpleToggleSettingsType::class, [
                'label' => 'three_brs.ui.security_settings.session_management.enabled',
            ])
            ->add('login_notifications', SimpleToggleSettingsType::class, [
                'label' => 'three_brs.ui.security_settings.login_notifications.enabled',
            ])
            ->add('password_login', SimpleToggleSettingsType::class, [
                'label' => 'three_brs.ui.security_settings.password_login.enabled',
            ])
            ->add('oauth', OAuthSettingsType::class)
        ;

        if ($scope === SettingsScope::ADMIN) {
            $builder
                ->add('oauth_admin_policy', OAuthAutoRegistrationPolicySettingsType::class, [
                    'translation_prefix' => 'three_brs.ui.security_settings.oauth_admin_policy',
                ])
                ->add('ip_whitelist', IpWhitelistSettingsType::class)
                ->add('ip_blacklist', IpBlacklistSettingsType::class)
            ;
        }

        if ($scope === SettingsScope::CUSTOMER) {
            $builder
                ->add('oauth_customer_policy', OAuthAutoRegistrationPolicySettingsType::class, [
                    'translation_prefix' => 'three_brs.ui.security_settings.oauth_customer_policy',
                    'include_default_locale' => false,
                ])
                ->add('account_deletion', AccountDeletionSettingsType::class)
            ;
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('scope');
        $resolver->setAllowedTypes('scope', SettingsScope::class);
    }

    public function getBlockPrefix(): string
    {
        return 'three_brs_security_settings';
    }
}
