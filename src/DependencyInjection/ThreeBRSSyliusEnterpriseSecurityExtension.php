<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener\AdminUserPasswordHistoryListener;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener\ShopUserPasswordHistoryListener;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\PasswordPolicy;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\PasswordExpirationChecker;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Validator\PasswordHistoryValidator;

class ThreeBRSSyliusEnterpriseSecurityExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $this->registerPasswordPolicies($container, $config['password_policy']);
        $this->registerPasswordHistory($container, $config['password_history']);
        $this->registerPasswordExpiration($container, $config['password_expiration']);
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
