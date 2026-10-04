<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\DependencyInjection\Compiler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use ThreeBRS\EnterpriseSecurityBundle\TwoFactor\CancelPendingSignInRequiredHandler;
use ThreeBRS\EnterpriseSecurityBundle\TwoFactor\PendingSignInCanceller;
use ThreeBRS\EnterpriseSecurityBundle\TwoFactor\TwoFactorAwareAuthenticationSuccessHandler;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\DependencyInjection\Compiler\TwoFactorFirewallPass;

#[CoversClass(TwoFactorFirewallPass::class)]
class TwoFactorFirewallPassTest extends TestCase
{
    private const SHOP_REQUIRED_HANDLER = 'security.authentication.authentication_required_handler.two_factor.shop';

    private const ADMIN_REQUIRED_HANDLER = 'security.authentication.authentication_required_handler.two_factor.admin';

    private const SHOP_CANCELLER = TwoFactorFirewallPass::PENDING_SIGN_IN_CANCELLER_ID_PREFIX . 'shop';

    private const ADMIN_CANCELLER = TwoFactorFirewallPass::PENDING_SIGN_IN_CANCELLER_ID_PREFIX . 'admin';

    private const SHOP_HANDLER = TwoFactorFirewallPass::CANCEL_PENDING_SIGN_IN_REQUIRED_HANDLER_ID_PREFIX . 'shop';

    private const ADMIN_HANDLER = TwoFactorFirewallPass::CANCEL_PENDING_SIGN_IN_REQUIRED_HANDLER_ID_PREFIX . 'admin';

    public function testRegistersNothingWhenNoFirewallHasTwoFactor(): void
    {
        $container = new ContainerBuilder();

        (new TwoFactorFirewallPass())->process($container);

        self::assertFalse($container->has(TwoFactorFirewallPass::SHOP_SUCCESS_HANDLER_ID));
        self::assertFalse($container->has(self::SHOP_CANCELLER));
        self::assertFalse($container->has(self::SHOP_HANDLER));
        self::assertFalse($container->has(self::ADMIN_CANCELLER));
        self::assertFalse($container->has(self::ADMIN_HANDLER));
    }

    public function testRegistersTheShopServicesWhenTheShopFirewallHasTwoFactor(): void
    {
        $container = $this->createContainer([self::SHOP_REQUIRED_HANDLER]);

        (new TwoFactorFirewallPass())->process($container);

        $successHandler = $container->getDefinition(TwoFactorFirewallPass::SHOP_SUCCESS_HANDLER_ID);
        self::assertSame(TwoFactorAwareAuthenticationSuccessHandler::class, $successHandler->getClass());
        self::assertTrue($successHandler->isPublic());
        self::assertEquals(new Reference(self::SHOP_REQUIRED_HANDLER), $successHandler->getArgument('$twoFactorAuthenticationRequiredHandler'));
        self::assertEquals(new Reference('sylius.authentication.success_handler'), $successHandler->getArgument('$defaultSuccessHandler'));

        $canceller = $container->getDefinition(self::SHOP_CANCELLER);
        self::assertSame(PendingSignInCanceller::class, $canceller->getClass());
        self::assertTrue($canceller->hasTag('kernel.event_subscriber'));
        self::assertEquals(new Reference('security.firewall_config.two_factor.shop'), $canceller->getArgument('$twoFactorFirewallConfig'));
        self::assertEquals(new Reference('scheb_two_factor.security.access.access_decider'), $canceller->getArgument('$twoFactorAccessDecider'));
        self::assertSame(['sylius_shop_login'], $canceller->getArgument('$signInRoutes'));
        self::assertSame(['three_brs_shop_two_factor_recovery_challenge'], $canceller->getArgument('$twoFactorRoutes'));

        $handler = $container->getDefinition(self::SHOP_HANDLER);
        self::assertSame(CancelPendingSignInRequiredHandler::class, $handler->getClass());
        self::assertSame([self::SHOP_REQUIRED_HANDLER, null, 0], $handler->getDecoratedService());
        self::assertEquals(new Reference(self::SHOP_HANDLER . '.inner'), $handler->getArgument('$inner'));
        self::assertEquals(new Reference(self::SHOP_CANCELLER), $handler->getArgument('$pendingSignInCanceller'));

        self::assertFalse($container->has(self::ADMIN_CANCELLER));
        self::assertFalse($container->has(self::ADMIN_HANDLER));
    }

    public function testRegistersOnlyTheCancellationForTheAdminFirewall(): void
    {
        $container = $this->createContainer([self::ADMIN_REQUIRED_HANDLER]);

        (new TwoFactorFirewallPass())->process($container);

        self::assertFalse($container->has(TwoFactorFirewallPass::SHOP_SUCCESS_HANDLER_ID));
        self::assertFalse($container->has(self::SHOP_CANCELLER));

        $canceller = $container->getDefinition(self::ADMIN_CANCELLER);
        self::assertEquals(new Reference('security.firewall_config.two_factor.admin'), $canceller->getArgument('$twoFactorFirewallConfig'));
        self::assertSame(['sylius_admin_login'], $canceller->getArgument('$signInRoutes'));
        self::assertSame(['three_brs_admin_two_factor_recovery_challenge'], $canceller->getArgument('$twoFactorRoutes'));

        self::assertSame(
            [self::ADMIN_REQUIRED_HANDLER, null, 0],
            $container->getDefinition(self::ADMIN_HANDLER)->getDecoratedService(),
        );
    }

    public function testKeepsServicesTheApplicationDefines(): void
    {
        $container = $this->createContainer([self::SHOP_REQUIRED_HANDLER]);
        $applicationSuccessHandler = new Definition(\stdClass::class);
        $applicationCanceller = new Definition(\stdClass::class);
        $container->setDefinition(TwoFactorFirewallPass::SHOP_SUCCESS_HANDLER_ID, $applicationSuccessHandler);
        $container->setDefinition(self::SHOP_CANCELLER, $applicationCanceller);

        (new TwoFactorFirewallPass())->process($container);

        self::assertSame($applicationSuccessHandler, $container->getDefinition(TwoFactorFirewallPass::SHOP_SUCCESS_HANDLER_ID));
        self::assertSame($applicationCanceller, $container->getDefinition(self::SHOP_CANCELLER));
        self::assertEquals(
            new Reference(self::SHOP_CANCELLER),
            $container->getDefinition(self::SHOP_HANDLER)->getArgument('$pendingSignInCanceller'),
        );
    }

    /**
     * @param list<string> $requiredHandlerIds
     */
    private function createContainer(array $requiredHandlerIds): ContainerBuilder
    {
        $container = new ContainerBuilder();
        foreach ($requiredHandlerIds as $id) {
            $container->setDefinition($id, new Definition(\stdClass::class));
        }

        return $container;
    }
}
