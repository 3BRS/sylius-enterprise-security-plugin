<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Form\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use ThreeBRS\EnterpriseSecurityBundle\PasswordExpiration\PasswordExpirationAdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Extension\AdminUserTypeExtension;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\PasswordLoginCheckerInterface;

#[CoversClass(AdminUserTypeExtension::class)]
class AdminUserTypeExtensionTest extends TestCase
{
    public function testAddsForcePasswordChangeWhenAdminPasswordLoginEnabled(): void
    {
        $builder = $this->createMock(FormBuilderInterface::class);
        $builder->expects(self::once())
            ->method('add')
            ->with('forcePasswordChange', CheckboxType::class, self::anything());

        $this->extension(adminPasswordLoginEnabled: true)
            ->buildForm($builder, ['data_class' => PasswordExpirationAdminUserInterface::class]);
    }

    public function testRemovesPasswordAndSkipsForcePasswordChangeWhenAdminPasswordLoginDisabled(): void
    {
        $builder = $this->createMock(FormBuilderInterface::class);
        $builder->expects(self::never())->method('add');
        $builder->expects(self::once())->method('remove')->with('plainPassword')->willReturnSelf();

        $this->extension(adminPasswordLoginEnabled: false)
            ->buildForm($builder, ['data_class' => PasswordExpirationAdminUserInterface::class]);
    }

    public function testDoesNothingForAnUnrelatedDataClass(): void
    {
        $builder = $this->createMock(FormBuilderInterface::class);
        $builder->expects(self::never())->method('add');
        $builder->expects(self::never())->method('remove');

        $this->extension(adminPasswordLoginEnabled: true)
            ->buildForm($builder, ['data_class' => \stdClass::class]);
    }

    protected function extension(bool $adminPasswordLoginEnabled): AdminUserTypeExtension
    {
        $checker = $this->createStub(PasswordLoginCheckerInterface::class);
        $checker->method('isEnabled')->willReturn($adminPasswordLoginEnabled);

        return new AdminUserTypeExtension($checker);
    }
}
