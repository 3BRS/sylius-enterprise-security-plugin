<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Functional;

use Doctrine\ORM\EntityManagerInterface;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Kernel;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsProviderInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsWriterInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\SecuritySetting;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener\FeatureToggleListener;

/**
 * Combination K15 and the §7 matrix of docs/manual-test-plan.md: a feature switched
 * off in Security Settings must take its URLs with it, and must not take anybody
 * else's.
 *
 * The cases are read out of the listener's own route map rather than transcribed
 * from the document, so a route added to the map is covered the day it is added and
 * the two cannot drift apart. Thirty-one routes make a Behat scenario each a poor
 * trade; the listener is where the decision is made, so that is where it is asked.
 */
class FeatureToggleRouteMatrixTest extends KernelTestCase
{
    /**
     * Administration screens that read customer data and therefore answer to the
     * customer switch, not to the administrator's own. Everything else follows its
     * route prefix.
     *
     * @var list<string>
     */
    protected const CUSTOMER_FEATURES_ADMINISTERED_BY_STAFF = [
        'three_brs_admin_customer_revoke_all_sessions',
        'three_brs_admin_customer_revoke_session',
        'three_brs_admin_locked_customers',
        'three_brs_admin_locked_customer_unlock',
        'three_brs_admin_account_deletions',
        'three_brs_admin_account_deletion_cancel',
    ];

    protected FeatureToggleListener $listener;

    protected SettingsWriterInterface $writer;

    protected SettingsProviderInterface $provider;

    protected EntityManagerInterface $entityManager;

    protected HttpKernelInterface $httpKernel;

    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel(['environment' => 'test', 'debug' => true]);
        $container = self::getContainer();

        /** @var FeatureToggleListener $listener */
        $listener = $container->get(FeatureToggleListener::class);
        $this->listener = $listener;

        /** @var SettingsWriterInterface $writer */
        $writer = $container->get(SettingsWriterInterface::class);
        $this->writer = $writer;

        /** @var SettingsProviderInterface $provider */
        $provider = $container->get(SettingsProviderInterface::class);
        $this->provider = $provider;

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get('doctrine.orm.entity_manager');
        $this->entityManager = $entityManager;

        /** @var HttpKernelInterface $httpKernel */
        $httpKernel = $container->get('http_kernel');
        $this->httpKernel = $httpKernel;

        $this->clearStoredSettings();
    }

    protected function tearDown(): void
    {
        $this->clearStoredSettings();

        parent::tearDown();

        // Booting the Sylius kernel leaves an exception handler on the stack.
        restore_exception_handler();
    }

    public function testEverySwitchClosesEveryRouteItGoverns(): void
    {
        foreach ($this->routeMap() as $route => [$feature, $scope]) {
            $this->switchFeature($feature, $scope, false);

            try {
                $this->listener->onKernelController($this->controllerEventFor($route));
                self::fail(sprintf('"%s" was still reachable with %s.%s switched off for %s.', $route, $feature, 'enabled', $scope->value));
            } catch (NotFoundHttpException) {
                self::assertTrue(true);
            } finally {
                $this->switchFeature($feature, $scope, true);
            }
        }
    }

    /**
     * Proves the listener reads the scope at all rather than any switch closing
     * everything. It does not prove the map names the right scope — it takes the
     * scope from the map — which is what the test below is for.
     */
    public function testNoSwitchClosesARouteTheOtherScopeGoverns(): void
    {
        foreach ($this->routeMap() as $route => [$feature, $scope]) {
            $other = $scope === SettingsScope::CUSTOMER ? SettingsScope::ADMIN : SettingsScope::CUSTOMER;

            $this->switchFeature($feature, $other, false);

            try {
                $this->listener->onKernelController($this->controllerEventFor($route));
            } catch (NotFoundHttpException) {
                self::fail(sprintf(
                    '"%s" is governed by the %s switch but closed when %s.enabled was switched off for %s.',
                    $route,
                    $scope->value,
                    $feature,
                    $other->value,
                ));
            } finally {
                $this->switchFeature($feature, $other, true);
            }
        }

        self::assertTrue(true, 'Every route survived the other scope being switched off.');
    }

    /**
     * The two tests above take the scope from the very map they exercise, so they
     * agree with whatever it says — moving a route to the wrong scope leaves them
     * green. This one brings an expectation from outside: a route is governed by
     * the side of the shop it lives on, except for the administration screens of a
     * customer feature, which §7 of the plan singles out as the pair most likely to
     * be got wrong. Those are named here, and naming them is the point: a route
     * that quietly joins or leaves that list has to be justified in this file.
     */
    public function testTheMapPutsEveryRouteUnderTheSideItBelongsTo(): void
    {
        foreach ($this->routeMap() as $route => [, $scope]) {
            $expected = match (true) {
                in_array($route, self::CUSTOMER_FEATURES_ADMINISTERED_BY_STAFF, true) => SettingsScope::CUSTOMER,
                str_starts_with($route, 'three_brs_shop_') => SettingsScope::CUSTOMER,
                str_starts_with($route, 'three_brs_admin_') => SettingsScope::ADMIN,
                default => self::fail(sprintf('Route "%s" follows neither naming convention.', $route)),
            };

            self::assertSame($expected, $scope, sprintf(
                'Route "%s" is governed by the %s switch; it should answer to the %s one.',
                $route,
                $scope->value,
                $expected->value,
            ));
        }
    }

    /**
     * @return array<string, array{string, SettingsScope}>
     */
    protected function routeMap(): array
    {
        /** @var array<string, array{string, SettingsScope}> $map */
        $map = (new ReflectionClass(FeatureToggleListener::class))->getConstant('ROUTE_MAP');
        self::assertNotEmpty($map, 'The listener governs no routes — the matrix would assert nothing.');

        return $map;
    }

    protected function controllerEventFor(string $route): ControllerEvent
    {
        $request = new Request();
        $request->attributes->set('_route', $route);

        return new ControllerEvent(
            $this->httpKernel,
            static fn (): null => null,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );
    }

    protected function switchFeature(string $feature, SettingsScope $scope, bool $enabled): void
    {
        $this->writer->set($feature . '.enabled', $scope, $enabled);
        $this->writer->flush();
        $this->provider->refresh();
    }

    protected function clearStoredSettings(): void
    {
        $this->entityManager->createQuery(sprintf('DELETE FROM %s', SecuritySetting::class))->execute();
        $this->provider->refresh();
    }
}
