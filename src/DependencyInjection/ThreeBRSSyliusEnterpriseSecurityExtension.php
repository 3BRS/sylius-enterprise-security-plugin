<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\AdminMagicLinkRequestHandler;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\GeoIpLookupInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\ShopMagicLinkRequestHandler;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings\Defaults\SettingsDefaultsBuilder;

class ThreeBRSSyliusEnterpriseSecurityExtension extends Extension implements PrependExtensionInterface
{
    public function prepend(ContainerBuilder $container): void
    {
        // Dedicated cache pool for the dynamic rate limiter. Symfony's default
        // `cache.rate_limiter` pool is only auto-created when `framework.rate_limiter`
        // is configured — we don't use Symfony's compile-time limiter registration
        // any more (DynamicRateLimiterFactory builds limiters at request time from
        // DB settings), so we ship our own pool and pin the storage to it.
        //
        // We back it with `cache.app` (not `cache.adapter.filesystem`) because
        // multi-pod deployments (Kubernetes, autoscaled containers) do not share
        // a filesystem — each pod would have its own counters and an attacker
        // could just retry on a different replica to bypass the limit. In a
        // single-instance deployment cache.app defaults to filesystem and the
        // behaviour is identical; in a clustered setup the app already needs
        // cache.app pointing at a shared backend (Redis / Memcached) for
        // Symfony's session, doctrine cache, etc. — we piggyback on that.
        $container->prependExtensionConfig('framework', [
            'cache' => [
                'pools' => [
                    'three_brs.rate_limiter.cache_pool' => [
                        'adapter' => 'cache.app',
                    ],
                ],
            ],
        ]);

        $container->prependExtensionConfig('sylius_mailer', [
            'emails' => [
                'three_brs_password_changed' => [
                    'template' => '@ThreeBRSSyliusEnterpriseSecurityPlugin/Email/passwordChanged.html.twig',
                    'enabled' => true,
                ],
                'three_brs_magic_link' => [
                    'template' => '@ThreeBRSSyliusEnterpriseSecurityPlugin/Email/magicLink.html.twig',
                    'enabled' => true,
                ],
                'three_brs_login_notification' => [
                    'template' => '@ThreeBRSSyliusEnterpriseSecurityPlugin/Email/loginNotification.html.twig',
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
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $this->registerOAuth($container, $config['oauth']);
        $this->registerMagicLink($container, $config['magic_link']);
        $this->registerPasskey($container, $config['passkey']);
        $this->registerAccountLockout($container, $config['account_lockout']);
        $this->registerSessionManagement($container, $config['session_management']);
        $this->registerLoginNotifications($container, $config['login_notifications']);
        $this->registerSecuritySettingsDefaults($container, $config);
    }

    /** @param array<string, mixed> $config */
    protected function registerSecuritySettingsDefaults(ContainerBuilder $container, array $config): void
    {
        $defaults = (new SettingsDefaultsBuilder())->build($config);
        $container->setParameter('three_brs.security_settings.defaults', $defaults);
    }

    /** @param array<string, mixed> $config */
    protected function registerSessionManagement(ContainerBuilder $container, array $config): void
    {
        $container->setParameter('three_brs.session_management.customer.enabled', (bool) $config['customer']['enabled']);
        $container->setParameter('three_brs.session_management.admin.enabled', (bool) $config['admin']['enabled']);

        $geoipService = $config['geoip_service'];
        if ($geoipService !== null && $geoipService !== '') {
            $container->setAlias(GeoIpLookupInterface::class, (string) $geoipService);
        }
    }

    /** @param array<string, array<string, mixed>> $config */
    protected function registerLoginNotifications(ContainerBuilder $container, array $config): void
    {
        $container->setParameter('three_brs.login_notifications.customer.enabled', (bool) $config['customer']['enabled']);
        $container->setParameter('three_brs.login_notifications.admin.enabled', (bool) $config['admin']['enabled']);
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

    /**
     * OAuth `enabled` flags, `default_locale` and `auto_register_allowed_email_domains`
     * are runtime-mutable via the Settings UI — they are read from the DB through
     * `SettingsProviderInterface`. Only the deployment-time secrets (client IDs,
     * client secrets, team / key IDs, private key paths) remain compile-time params.
     *
     * @param array<string, mixed> $config
     */
    protected function registerOAuth(ContainerBuilder $container, array $config): void
    {
        foreach (['customer', 'admin'] as $group) {
            $google = $config[$group]['google'];
            $container->setParameter(sprintf('three_brs.oauth.%s.google.client_id', $group), $google['client_id']);
            $container->setParameter(sprintf('three_brs.oauth.%s.google.client_secret', $group), $google['client_secret']);

            $apple = $config[$group]['apple'];
            $container->setParameter(sprintf('three_brs.oauth.%s.apple.client_id', $group), $apple['client_id']);
            $container->setParameter(sprintf('three_brs.oauth.%s.apple.team_id', $group), $apple['team_id']);
            $container->setParameter(sprintf('three_brs.oauth.%s.apple.key_id', $group), $apple['key_id']);
            $container->setParameter(sprintf('three_brs.oauth.%s.apple.private_key_path', $group), $apple['private_key_path']);
        }
    }

    public function getAlias(): string
    {
        return 'three_brs_sylius_enterprise_security';
    }
}
