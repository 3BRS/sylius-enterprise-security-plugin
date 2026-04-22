<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin;

use Sylius\Bundle\CoreBundle\Application\SyliusPluginTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\DependencyInjection\ThreeBRSSyliusEnterpriseSecurityExtension;

class ThreeBRSSyliusEnterpriseSecurityPlugin extends Bundle implements PrependExtensionInterface
{
    use SyliusPluginTrait;

    public function getContainerExtension(): ThreeBRSSyliusEnterpriseSecurityExtension
    {
        return new ThreeBRSSyliusEnterpriseSecurityExtension();
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
    }
}
