<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\Compiler\RegisterEnvVarProcessorsPass;
use Symfony\Component\DependencyInjection\Compiler\ValidateEnvPlaceholdersPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\EnvVarProcessor;
use Symfony\Component\DependencyInjection\ParameterBag\EnvPlaceholderParameterBag;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\DependencyInjection\Configuration;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\DependencyInjection\ThreeBRSSyliusEnterpriseSecurityExtension;

#[CoversClass(Configuration::class)]
class ConfigurationTest extends TestCase
{
    public function testTheSessionLifetimeDefaultsToNull(): void
    {
        self::assertNull($this->process([])['session_management']['lifetime']);
    }

    /**
     * @return iterable<string, array{int|null}>
     */
    public static function acceptedSessionLifetimeProvider(): iterable
    {
        yield 'null' => [null];
        yield 'seconds' => [3600];
    }

    #[DataProvider('acceptedSessionLifetimeProvider')]
    public function testTheSessionLifetimeAcceptsSecondsOrNull(?int $lifetime): void
    {
        $config = $this->process(['session_management' => ['lifetime' => $lifetime]]);

        self::assertSame($lifetime, $config['session_management']['lifetime']);
    }

    /**
     * @return iterable<string, array{int|string}>
     */
    public static function refusedSessionLifetimeProvider(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-60];
        yield 'duration string' => ['1 hour'];
    }

    #[DataProvider('refusedSessionLifetimeProvider')]
    public function testTheSessionLifetimeRefusesAnythingButPositiveSeconds(int|string $lifetime): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process(['session_management' => ['lifetime' => $lifetime]]);
    }

    public function testTheSessionLifetimeAcceptsAnEnvironmentVariable(): void
    {
        $parameters = new EnvPlaceholderParameterBag();
        $container = new ContainerBuilder($parameters);
        $container->registerExtension(new ThreeBRSSyliusEnterpriseSecurityExtension());
        // Resolved through the bag first, as merging the configuration does before this pass runs.
        $container->loadFromExtension('three_brs_sylius_enterprise_security', $parameters->resolveValue([
            'session_management' => ['lifetime' => '%env(int:SESSION_LIFETIME)%'],
        ]));

        // FrameworkBundle registers the processor that gives `int:` its type.
        $container->register('env_var_processor', EnvVarProcessor::class)->addTag('container.env_var_processor');
        (new RegisterEnvVarProcessorsPass())->process($container);
        (new ValidateEnvPlaceholdersPass())->process($container);

        $this->addToAssertionCount(1);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    protected function process(array $config): array
    {
        return (new Processor())->processConfiguration(new Configuration(), [$config]);
    }
}
