<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Form\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormBuilderInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Extension\ShopUserTypeExtension;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\PasswordLoginCheckerInterface;

#[CoversClass(ShopUserTypeExtension::class)]
class ShopUserTypeExtensionTest extends TestCase
{
    public function testRemovesPasswordWhenCustomerPasswordLoginDisabled(): void
    {
        $builder = $this->createMock(FormBuilderInterface::class);
        $builder->method('has')->willReturn(true);
        $builder->expects(self::once())->method('remove')->with('plainPassword')->willReturnSelf();

        $this->extension(customerPasswordLoginEnabled: false)->buildForm($builder, []);
    }

    public function testKeepsPasswordWhenCustomerPasswordLoginEnabled(): void
    {
        $builder = $this->createMock(FormBuilderInterface::class);
        $builder->expects(self::never())->method('remove');

        $this->extension(customerPasswordLoginEnabled: true)->buildForm($builder, []);
    }

    protected function extension(bool $customerPasswordLoginEnabled): ShopUserTypeExtension
    {
        $checker = $this->createStub(PasswordLoginCheckerInterface::class);
        $checker->method('isEnabled')->willReturn($customerPasswordLoginEnabled);

        return new ShopUserTypeExtension($checker);
    }
}
