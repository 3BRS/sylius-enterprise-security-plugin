<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener\AdminUserPasswordHistoryListener;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener\PasswordChangeNotificationListener;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener\ShopUserPasswordHistoryListener;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\PasswordPolicy;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\TwoFactorMode;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\AdminMagicLinkRequestHandler;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\PasswordExpirationChecker;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\ShopMagicLinkRequestHandler;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\TwoFactorEnforcementChecker;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Validator\PasswordHistoryValidator;

class ThreeBRSSyliusEnterpriseSecurityExtension extends Extension implements PrependExtensionInterface
{
    public function prepend(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('sylius_mailer', [
            'emails' => [
                'three_brs_password_changed' => [
                    'subject' => 'three_brs.emails.password_changed.subject',
                    'template' => '@ThreeBRSSyliusEnterpriseSecurityPlugin/Email/passwordChanged.html.twig',
                    'enabled' => true,
                ],
                'three_brs_magic_link' => [
                    'template' => '@ThreeBRSSyliusEnterpriseSecurityPlugin/Email/magicLink.html.twig',
                    'enabled' => true,
                ],
            ],
        ]);

        $config = $this->processConfiguration(
            new Configuration(),
            $container->getExtensionConfig($this->getAlias()),
        );
        $twoFactor = $config['two_factor_authentication'];

        $container->setParameter('three_brs.two_factor.issuer', $twoFactor['issuer']);
        $container->setParameter('three_brs.two_factor.customer.recovery_codes_enabled', $twoFactor['recovery_codes']['customer']['enabled']);
        $container->setParameter('three_brs.two_factor.customer.recovery_codes_count', $twoFactor['recovery_codes']['customer']['count']);
        $container->setParameter('three_brs.two_factor.admin.recovery_codes_enabled', $twoFactor['recovery_codes']['admin']['enabled']);
        $container->setParameter('three_brs.two_factor.admin.recovery_codes_count', $twoFactor['recovery_codes']['admin']['count']);
        $container->setParameter('three_brs.two_factor.trusted_device_enabled', $twoFactor['trusted_device']['enabled']);
        $container->setParameter('three_brs.two_factor.trusted_device_lifetime', (int) $twoFactor['trusted_device']['days'] * 86400);

        $this->prependRateLimiters($container, $config['rate_limit']);
    }

    /**
     * Auto-registers Symfony framework.rate_limiter services for each enabled (group, action) pair.
     * Symfony FrameworkBundle then exposes them as `limiter.three_brs_<group>_<action>` services.
     *
     * @param array<string, array<string, array<string, mixed>>> $config
     */
    protected function prependRateLimiters(ContainerBuilder $container, array $config): void
    {
        $rateLimiters = [];

        foreach ($config as $group => $actions) {
            foreach ($actions as $action => $settings) {
                if ($settings['enabled'] !== true) {
                    continue;
                }
                $rateLimiters[sprintf('three_brs_%s_%s', $group, $action)] = [
                    'policy' => 'fixed_window',
                    'limit' => $settings['limit'],
                    'interval' => $settings['interval'],
                ];
            }
        }

        if ($rateLimiters === []) {
            return;
        }

        $container->prependExtensionConfig('framework', [
            'rate_limiter' => $rateLimiters,
        ]);
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $this->registerPasswordPolicies($container, $config['password_policy']);
        $this->registerPasswordHistory($container, $config['password_history']);
        $this->registerPasswordExpiration($container, $config['password_expiration']);
        $this->registerPasswordChangeNotification($container, $config['password_change_notification']);
        $this->registerTwoFactorAuthentication($container, $config['two_factor_authentication']);
        $this->registerOAuth($container, $config['oauth']);
        $this->registerMagicLink($container, $config['magic_link']);
        $this->registerPasskey($container, $config['passkey']);
        $this->registerAccountLockout($container, $config['account_lockout']);
        $this->registerRateLimit($container, $config['rate_limit']);
    }

    /** @param array<string, array<string, mixed>> $config */
    protected function registerAccountLockout(ContainerBuilder $container, array $config): void
    {
        foreach (['customer', 'admin'] as $group) {
            $groupConfig = $config[$group];
            $container->setParameter(sprintf('three_brs.account_lockout.%s.enabled', $group), (bool) $groupConfig['enabled']);
            $container->setParameter(sprintf('three_brs.account_lockout.%s.max_attempts', $group), (int) $groupConfig['max_attempts']);
            $container->setParameter(
                sprintf('three_brs.account_lockout.%s.auto_unlock_after', $group),
                $groupConfig['auto_unlock_after'] === null ? null : (int) $groupConfig['auto_unlock_after'],
            );
        }
    }

    /** @param array<string, array<string, array<string, mixed>>> $config */
    protected function registerRateLimit(ContainerBuilder $container, array $config): void
    {
        foreach ($config as $group => $actions) {
            foreach ($actions as $action => $settings) {
                $container->setParameter(
                    sprintf('three_brs.rate_limit.%s.%s.enabled', $group, $action),
                    (bool) $settings['enabled'],
                );
            }
        }
    }

    /** @param array<string, mixed> $config */
    protected function registerPasskey(ContainerBuilder $container, array $config): void
    {
        $customerEnabled = (bool) $config['customer']['enabled'];
        $adminEnabled = (bool) $config['admin']['enabled'];

        $rpId = (string) ($config['rp_id'] ?? '');
        $rpName = (string) ($config['rp_name'] ?? '');

        if (($customerEnabled || $adminEnabled) && ($rpId === '' || $rpName === '')) {
            throw new \InvalidArgumentException(
                'three_brs_sylius_enterprise_security.passkey: rp_id and rp_name must be configured when passkey is enabled for customer or admin.',
            );
        }

        $container->setParameter('three_brs.passkey.customer.enabled', $customerEnabled);
        $container->setParameter('three_brs.passkey.admin.enabled', $adminEnabled);
        $container->setParameter('three_brs.passkey.rp_id', $rpId);
        $container->setParameter('three_brs.passkey.rp_name', $rpName);
        $container->setParameter('three_brs.passkey.skip_2fa_when_user_verified', (bool) $config['skip_2fa_when_user_verified']);
    }

    /** @param array<string, array<string, mixed>> $config */
    protected function registerMagicLink(ContainerBuilder $container, array $config): void
    {
        $container->getDefinition(ShopMagicLinkRequestHandler::class)
            ->setArgument('$enabled', $config['customer']['enabled'])
            ->setArgument('$expirationSeconds', $config['customer']['expiration_seconds'])
        ;

        $container->getDefinition(AdminMagicLinkRequestHandler::class)
            ->setArgument('$enabled', $config['admin']['enabled'])
            ->setArgument('$expirationSeconds', $config['admin']['expiration_seconds'])
        ;

        $container->setParameter('three_brs.magic_link.customer.enabled', $config['customer']['enabled']);
        $container->setParameter('three_brs.magic_link.admin.enabled', $config['admin']['enabled']);
    }

    /** @param array<string, mixed> $config */
    protected function registerOAuth(ContainerBuilder $container, array $config): void
    {
        foreach (['customer', 'admin'] as $group) {
            $google = $config[$group]['google'];
            $container->setParameter(sprintf('three_brs.oauth.%s.google.enabled', $group), $google['enabled']);
            $container->setParameter(sprintf('three_brs.oauth.%s.google.client_id', $group), $google['client_id']);
            $container->setParameter(sprintf('three_brs.oauth.%s.google.client_secret', $group), $google['client_secret']);

            $apple = $config[$group]['apple'];
            $container->setParameter(sprintf('three_brs.oauth.%s.apple.enabled', $group), $apple['enabled']);
            $container->setParameter(sprintf('three_brs.oauth.%s.apple.client_id', $group), $apple['client_id']);
            $container->setParameter(sprintf('three_brs.oauth.%s.apple.team_id', $group), $apple['team_id']);
            $container->setParameter(sprintf('three_brs.oauth.%s.apple.key_id', $group), $apple['key_id']);
            $container->setParameter(sprintf('three_brs.oauth.%s.apple.private_key_path', $group), $apple['private_key_path']);
        }

        $container->setParameter(
            'three_brs.oauth.admin.auto_register_allowed_email_domains',
            $config['admin']['auto_register_allowed_email_domains'],
        );
        $container->setParameter(
            'three_brs.oauth.admin.default_locale',
            $config['admin']['default_locale'],
        );
    }

    /** @param array<string, mixed> $config */
    protected function registerTwoFactorAuthentication(ContainerBuilder $container, array $config): void
    {
        $container->getDefinition(TwoFactorEnforcementChecker::class)
            ->setArgument('$customerMode', TwoFactorMode::from($config['customer']['mode']))
            ->setArgument('$adminMode', TwoFactorMode::from($config['admin']['mode']))
        ;
    }

    public function getAlias(): string
    {
        return 'three_brs_sylius_enterprise_security';
    }

    /** @param array<string, array<string, mixed>> $config */
    private function registerPasswordHistory(ContainerBuilder $container, array $config): void
    {
        $container->getDefinition(PasswordHistoryValidator::class)
            ->setArgument('$customerEnabled', $config['customer']['enabled'])
            ->setArgument('$customerCount', $config['customer']['count'])
            ->setArgument('$adminEnabled', $config['admin']['enabled'])
            ->setArgument('$adminCount', $config['admin']['count'])
        ;

        $container->getDefinition(ShopUserPasswordHistoryListener::class)
            ->setArgument('$enabled', $config['customer']['enabled'])
            ->setArgument('$count', $config['customer']['count'])
        ;

        $container->getDefinition(AdminUserPasswordHistoryListener::class)
            ->setArgument('$enabled', $config['admin']['enabled'])
            ->setArgument('$count', $config['admin']['count'])
        ;
    }

    /** @param array<string, array<string, mixed>> $config */
    private function registerPasswordExpiration(ContainerBuilder $container, array $config): void
    {
        $container->getDefinition(PasswordExpirationChecker::class)
            ->setArgument('$customerEnabled', $config['customer']['enabled'])
            ->setArgument('$customerDays', $config['customer']['days'])
            ->setArgument('$adminEnabled', $config['admin']['enabled'])
            ->setArgument('$adminDays', $config['admin']['days'])
        ;
    }

    /** @param array<string, array<string, mixed>> $config */
    private function registerPasswordChangeNotification(ContainerBuilder $container, array $config): void
    {
        $container->getDefinition(PasswordChangeNotificationListener::class)
            ->setArgument('$customerEnabled', $config['customer']['enabled'])
            ->setArgument('$adminEnabled', $config['admin']['enabled'])
        ;
    }

    /** @param array<string, array<string, mixed>> $config */
    private function registerPasswordPolicies(ContainerBuilder $container, array $config): void
    {
        foreach (['customer', 'admin'] as $group) {
            $groupConfig = $config[$group];
            $container
                ->register(sprintf('three_brs.password_policy.%s', $group), PasswordPolicy::class)
                ->setArguments([
                    '$minLength' => $groupConfig['min_length'],
                    '$maxLength' => $groupConfig['max_length'],
                    '$requireUppercase' => $groupConfig['require_uppercase'],
                    '$requireLowercase' => $groupConfig['require_lowercase'],
                    '$requireNumbers' => $groupConfig['require_numbers'],
                    '$requireSpecialCharacters' => $groupConfig['require_special_characters'],
                ])
                ->setPublic(false)
            ;
        }
    }
}
