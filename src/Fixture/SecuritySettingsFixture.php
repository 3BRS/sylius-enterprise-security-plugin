<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Fixture;

use Sylius\Bundle\FixturesBundle\Fixture\AbstractFixture;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\SecuritySettingRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings\Defaults\SettingsDefaultsProviderInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings\SettingsScope;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings\SettingsWriterInterface;

class SecuritySettingsFixture extends AbstractFixture implements SecuritySettingsFixtureInterface
{
    public function __construct(
        protected SettingsDefaultsProviderInterface $defaultsProvider,
        protected SettingsWriterInterface $writer,
        protected SecuritySettingRepositoryInterface $repository,
    ) {
    }

    public function getName(): string
    {
        return 'three_brs_security_settings';
    }

    /** @param array<mixed> $options */
    public function load(array $options): void
    {
        if ($options['reset'] === true) {
            $this->repository->deleteAll();
        }

        foreach ($this->defaultsProvider->all() as $scopeKey => $values) {
            $scope = SettingsScope::from($scopeKey);
            foreach ($values as $path => $value) {
                if (array_key_exists($path, $options['overrides'][$scopeKey] ?? [])) {
                    $value = $options['overrides'][$scopeKey][$path];
                }
                $this->writer->set($path, $scope, $value);
            }
        }

        $this->writer->flush();
    }

    protected function configureOptionsNode(ArrayNodeDefinition $optionsNode): void
    {
        $optionsNode
            ->children()
                ->booleanNode('reset')->defaultTrue()->end()
                ->arrayNode('overrides')
                    ->defaultValue([])
                    ->arrayPrototype()
                        ->variablePrototype()->end()
                    ->end()
                ->end()
            ->end()
        ;
    }
}
