<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\PasswordPolicy;

class ThreeBRSSyliusEnterpriseSecurityExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $this->registerPasswordPolicies($container, $config['password_policy']);
    }

    public function getAlias(): string
    {
        return 'three_brs_sylius_enterprise_security';
    }

    /** @param array<string, array<string, mixed>> $config */
    private function registerPasswordPolicies(ContainerBuilder $container, array $config): void
    {
        foreach (['customer', 'admin'] as $group) {
            $groupConfig = $config[$group];
            $container
                ->register(sprintf('three_brs.password_policy.%s', $group), PasswordPolicy::class)
                ->setArguments([
                    $groupConfig['min_length'],
                    $groupConfig['max_length'],
                    $groupConfig['require_uppercase'],
                    $groupConfig['require_lowercase'],
                    $groupConfig['require_numbers'],
                    $groupConfig['require_special_characters'],
                ])
                ->setPublic(false)
            ;
        }
    }
}
