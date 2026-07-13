<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Form\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\AdminBundle\Form\Type\ShopUserType;
use Sylius\Bundle\CoreBundle\Form\Type\User\ShopUserType as BaseShopUserType;
use Symfony\Component\Form\FormBuilderInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
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

    public function testCoversBothTheAdminAndTheBaseShopUserType(): void
    {
        // Sylius swaps the admin type for the base one while handling the submit of the customer form,
        // so the password field would come back on re-render if only the admin type was covered.
        self::assertSame(
            [BaseShopUserType::class, ShopUserType::class],
            iterator_to_array(ShopUserTypeExtension::getExtendedTypes()),
        );
    }

    protected function extension(bool $customerPasswordLoginEnabled): ShopUserTypeExtension
    {
        // Answers per scope, so reading the admin toggle here instead of the customer one would show up.
        $checker = $this->createStub(PasswordLoginCheckerInterface::class);
        $checker->method('isEnabled')->willReturnCallback(
            static fn (SettingsScope $scope): bool => $scope === SettingsScope::CUSTOMER
                ? $customerPasswordLoginEnabled
                : true,
        );

        return new ShopUserTypeExtension($checker);
    }
}
