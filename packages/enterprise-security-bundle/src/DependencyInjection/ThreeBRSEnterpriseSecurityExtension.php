<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use ThreeBRS\SyliusEnterpriseSecurityBundle\Model\PasswordPolicy;
use ThreeBRS\SyliusEnterpriseSecurityBundle\Validator\PasswordPolicyValidator;

class ThreeBRSEnterpriseSecurityExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $policyReferences = $this->registerPasswordPolicies($container, $config['password_policy']);

        $container->getDefinition(PasswordPolicyValidator::class)
            ->setArgument(0, $policyReferences)
        ;
    }

    public function getAlias(): string
    {
        return 'three_brs_enterprise_security';
    }

    /**
     * @param array<string, array<string, mixed>> $policies
     *
     * @return array<string, Reference>
     */
    private function registerPasswordPolicies(ContainerBuilder $container, array $policies): array
    {
        $references = [];

        foreach ($policies as $name => $policyConfig) {
            $serviceId = sprintf('three_brs.password_policy.%s', $name);
            $container
                ->register($serviceId, PasswordPolicy::class)
                ->setArguments([
                    $policyConfig['min_length'],
                    $policyConfig['max_length'],
                    $policyConfig['require_uppercase'],
                    $policyConfig['require_lowercase'],
                    $policyConfig['require_numbers'],
                    $policyConfig['require_special_characters'],
                ])
                ->setPublic(false)
            ;
            $references[$name] = new Reference($serviceId);
        }

        return $references;
    }
}
