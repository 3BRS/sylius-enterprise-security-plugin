<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin;

use Sylius\Bundle\CoreBundle\Application\SyliusPluginTrait;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\DependencyInjection\Compiler\RateLimiterServiceLocatorPass;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\DependencyInjection\ThreeBRSSyliusEnterpriseSecurityExtension;

class ThreeBRSSyliusEnterpriseSecurityPlugin extends Bundle implements PrependExtensionInterface
{
    use SyliusPluginTrait;

    public function getContainerExtension(): ThreeBRSSyliusEnterpriseSecurityExtension
    {
        return new ThreeBRSSyliusEnterpriseSecurityExtension();
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new RateLimiterServiceLocatorPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, -100);
    }

    public function prepend(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('framework', [
            'validation' => [
                'mapping' => [
                    'paths' => [__DIR__ . '/Resources/config/validation'],
                ],
            ],
        ]);

        $container->prependExtensionConfig('doctrine', [
            'orm' => [
                'mappings' => [
                    'ThreeBRSSyliusEnterpriseSecurityPlugin' => [
                        'type' => 'attribute',
                        'dir' => __DIR__ . '/Entity',
                        'prefix' => 'ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity',
                        'alias' => 'ThreeBRSSyliusEnterpriseSecurityPlugin',
                    ],
                ],
            ],
        ]);
    }
}
