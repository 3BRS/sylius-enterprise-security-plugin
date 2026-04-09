<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('three_brs_enterprise_security');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->arrayNode('password_policy')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->integerNode('min_length')->defaultValue(8)->min(1)->end()
                            ->scalarNode('max_length')->defaultNull()->validate()->ifTrue(static fn ($v) => $v !== null && !is_int($v))->thenInvalid('max_length must be a positive integer or null')->end()->end()
                            ->booleanNode('require_uppercase')->defaultFalse()->end()
                            ->booleanNode('require_lowercase')->defaultFalse()->end()
                            ->booleanNode('require_numbers')->defaultFalse()->end()
                            ->booleanNode('require_special_characters')->defaultFalse()->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
