<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Kernel;
use ThreeBRS\EnterpriseSecurityBundle\Settings\Defaults\SettingsDefaultsProviderInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsProviderInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsWriterInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\SecuritySetting;

/**
 * Combination K17 of docs/manual-test-plan.md, which the plan asks to work through
 * "systematically across all dual-scope features" — so the cases come from the
 * defaults map rather than from a list somebody has to remember to extend. Every
 * setting the plugin offers to both customers and administrators is covered, and a
 * new one is covered the day it is added.
 *
 * A scope leak is invisible from a single-scope scenario: turning a feature off for
 * customers and finding it off is the expected result either way. What has to be
 * asserted is the scope nobody touched.
 */
class SettingsScopeIsolationTest extends KernelTestCase
{
    protected SettingsDefaultsProviderInterface $defaults;

    protected SettingsProviderInterface $provider;

    protected SettingsWriterInterface $writer;

    protected EntityManagerInterface $entityManager;

    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel(['environment' => 'test', 'debug' => true]);
        $container = self::getContainer();

        /** @var SettingsDefaultsProviderInterface $defaults */
        $defaults = $container->get(SettingsDefaultsProviderInterface::class);
        $this->defaults = $defaults;

        /** @var SettingsProviderInterface $provider */
        $provider = $container->get(SettingsProviderInterface::class);
        $this->provider = $provider;

        /** @var SettingsWriterInterface $writer */
        $writer = $container->get(SettingsWriterInterface::class);
        $this->writer = $writer;

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get('doctrine.orm.entity_manager');
        $this->entityManager = $entityManager;

        $this->clearStoredSettings();
    }

    protected function tearDown(): void
    {
        $this->clearStoredSettings();

        parent::tearDown();

        // Booting the Sylius kernel leaves an exception handler on the stack.
        restore_exception_handler();
    }

    public function testChangingEverySettingForCustomersLeavesAdministratorsAlone(): void
    {
        $this->assertScopeSurvives(SettingsScope::CUSTOMER, SettingsScope::ADMIN);
    }

    public function testChangingEverySettingForAdministratorsLeavesCustomersAlone(): void
    {
        $this->assertScopeSurvives(SettingsScope::ADMIN, SettingsScope::CUSTOMER);
    }

    /**
     * The two scopes are written in one pass rather than one setting at a time:
     * the failure this guards against is a writer or a repository that drops the
     * scope from its lookup, and that shows up on the first path either way.
     */
    protected function assertScopeSurvives(SettingsScope $written, SettingsScope $untouched): void
    {
        $shared = $this->sharedPaths();
        self::assertNotEmpty($shared, 'No setting is offered to both scopes — the defaults map is not what this test assumes.');

        foreach ($shared as $path => $value) {
            $changed = $this->changed($value);
            self::assertNotSame($value, $changed, sprintf('Nothing to write for "%s" — the test would assert nothing.', $path));

            $this->writer->set($path, $written, $changed);
        }
        $this->writer->flush();
        $this->provider->refresh();

        $defaults = $this->defaults->all();

        foreach (array_keys($shared) as $path) {
            self::assertSame(
                $defaults[$untouched->value][$path],
                $this->provider->get($path, $untouched),
                sprintf(
                    'Writing "%s" for the %s scope changed what the %s scope reads.',
                    $path,
                    $written->value,
                    $untouched->value,
                ),
            );
        }
    }

    /**
     * Paths the plugin offers to customers and administrators alike. Anything
     * scoped to one of them (account deletion, for instance) has no other scope to
     * bleed into and is not a case here.
     *
     * @return array<string, mixed>
     */
    protected function sharedPaths(): array
    {
        $all = $this->defaults->all();

        return array_intersect_key(
            $all[SettingsScope::CUSTOMER->value] ?? [],
            $all[SettingsScope::ADMIN->value] ?? [],
        );
    }

    /**
     * A value that differs from the one given, whatever its type — the assertion
     * is only worth anything if the write actually changes something.
     */
    protected function changed(mixed $value): mixed
    {
        return match (true) {
            is_bool($value) => !$value,
            is_int($value), is_float($value) => $value + 1,
            is_string($value) => $value . ' (changed)',
            is_array($value) => [...$value, 'changed by the test'],
            default => 'set by the test',
        };
    }

    protected function clearStoredSettings(): void
    {
        $this->entityManager->createQuery(sprintf('DELETE FROM %s', SecuritySetting::class))->execute();
        $this->provider->refresh();
    }
}
