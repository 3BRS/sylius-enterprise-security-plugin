<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type\Settings;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;

/**
 * @extends AbstractType<array<string, mixed>>
 */
class SecuritySettingsType extends AbstractType implements SecuritySettingsTypeInterface
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var SettingsScope $scope */
        $scope = $options['scope'];

        $builder
            ->add('password_policy', PasswordPolicySettingsType::class)
            ->add('password_history', PasswordHistorySettingsType::class)
            ->add('password_expiration', PasswordExpirationSettingsType::class)
            ->add('password_change_notification', SimpleToggleSettingsType::class, [
                'label' => 'three_brs.ui.security_settings.password_change_notification.enabled',
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
            ->add('oauth', OAuthSettingsType::class)
        ;

        if ($scope === SettingsScope::ADMIN) {
            $builder
                ->add('oauth_admin_policy', OAuthAdminPolicySettingsType::class, [
                    'translation_prefix' => 'three_brs.ui.security_settings.oauth_admin_policy',
                    'invalid_domain_translation_key' => 'three_brs.oauth_admin_policy.invalid_domain',
                ])
                ->add('ip_whitelist', IpWhitelistSettingsType::class)
            ;
        }

        if ($scope === SettingsScope::CUSTOMER) {
            $builder
                ->add('oauth_customer_policy', OAuthAdminPolicySettingsType::class, [
                    'translation_prefix' => 'three_brs.ui.security_settings.oauth_customer_policy',
                    'invalid_domain_translation_key' => 'three_brs.oauth_customer_policy.invalid_domain',
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
