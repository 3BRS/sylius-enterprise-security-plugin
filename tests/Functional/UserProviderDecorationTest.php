<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Kernel;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Security\AccountStateAwareUserProvider;

/**
 * The decoration is the whole mechanism — nothing calls the class directly, and
 * a renamed Sylius service id or a lost decorates: line would leave sessions of
 * disabled accounts open again with every unit test still green.
 */
class UserProviderDecorationTest extends KernelTestCase
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

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerServiceProvider(): iterable
    {
        yield 'shop' => ['sylius.shop_user_provider.email_or_name_based'];
        yield 'admin' => ['sylius.admin_user_provider.email_or_name_based'];
    }

    #[DataProvider('providerServiceProvider')]
    public function testTheUserProviderRefusesDisabledAccountsOnRefresh(string $serviceId): void
    {
        self::bootKernel(['environment' => 'test', 'debug' => true]);

        self::assertInstanceOf(AccountStateAwareUserProvider::class, self::getContainer()->get($serviceId));
    }
}
