<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Functional;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Kernel;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener\FeatureToggleListener;

/**
 * The listener is what makes the Security Settings switches reach the endpoints;
 * unregistered, every unit test around it still passes and every switched-off
 * feature is reachable by URL again.
 */
class FeatureToggleListenerWiringTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Booting the Sylius kernel leaves an exception handler on the stack.
        restore_exception_handler();
    }

    public function testTheListenerRunsOnEveryController(): void
    {
        self::bootKernel(['environment' => 'test', 'debug' => true]);

        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = self::getContainer()->get('event_dispatcher');

        $classes = array_map(
            static fn (array $listener): string => is_array($listener) ? get_class($listener[0]) : '',
            $dispatcher->getListeners('kernel.controller'),
        );

        self::assertContains(FeatureToggleListener::class, $classes);
    }
}
