<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\RateLimit\RateLimitGuard;

class RateLimiterServiceLocatorPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(RateLimitGuard::class)) {
            return;
        }

        $references = [];
        foreach ($container->findTaggedServiceIds('rate_limiter') as $id => $tags) {
            if (!str_starts_with($id, 'limiter.three_brs_')) {
                continue;
            }
            $references[$id] = new Reference($id);
        }

        $locator = ServiceLocatorTagPass::register($container, $references);

        $container->getDefinition(RateLimitGuard::class)
            ->setArgument('$limiterLocator', $locator);
    }
}
