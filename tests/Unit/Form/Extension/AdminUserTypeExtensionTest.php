<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Form\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use ThreeBRS\EnterpriseSecurityBundle\PasswordExpiration\PasswordExpirationAdminUserInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
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
        $builder->method('has')->with('plainPassword')->willReturn(true);
        $builder->expects(self::once())->method('remove')->with('plainPassword')->willReturnSelf();

        $this->extension(adminPasswordLoginEnabled: false)
            ->buildForm($builder, ['data_class' => PasswordExpirationAdminUserInterface::class]);
    }

    public function testRemovesPasswordEvenWhenAdminUserLacksThePasswordExpirationInterface(): void
    {
        $builder = $this->createMock(FormBuilderInterface::class);
        $builder->expects(self::never())->method('add');
        $builder->method('has')->with('plainPassword')->willReturn(true);
        $builder->expects(self::once())->method('remove')->with('plainPassword')->willReturnSelf();

        $this->extension(adminPasswordLoginEnabled: false)
            ->buildForm($builder, ['data_class' => \stdClass::class]);
    }

    public function testDoesNotRemovePasswordWhenTheFieldIsAbsentAndAdminPasswordLoginDisabled(): void
    {
        $builder = $this->createMock(FormBuilderInterface::class);
        $builder->expects(self::never())->method('add');
        $builder->method('has')->with('plainPassword')->willReturn(false);
        $builder->expects(self::never())->method('remove');

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

    public function testDropsTheCreateValidationGroupOfANewAdminWhenAdminPasswordLoginDisabled(): void
    {
        // Without this the NotBlank on plainPassword rejects an administrator nobody can give a password to.
        self::assertSame(
            ['sylius'],
            $this->resolveValidationGroups(adminPasswordLoginEnabled: false, adminUserId: null),
        );
    }

    public function testKeepsTheCreateValidationGroupWhenAdminPasswordLoginEnabled(): void
    {
        self::assertSame(
            ['sylius', 'sylius_user_create'],
            $this->resolveValidationGroups(adminPasswordLoginEnabled: true, adminUserId: null),
        );
    }

    public function testLeavesTheGroupsOfAnExistingAdminAlone(): void
    {
        // Sylius only adds the create group for a user with no id, so there is nothing to strip here.
        self::assertSame(
            ['sylius'],
            $this->resolveValidationGroups(adminPasswordLoginEnabled: false, adminUserId: 42),
        );
    }

    /** @return list<string> */
    protected function resolveValidationGroups(bool $adminPasswordLoginEnabled, ?int $adminUserId): array
    {
        $adminUser = $this->createStub(AdminUserInterface::class);
        $adminUser->method('getId')->willReturn($adminUserId);

        $form = $this->createStub(FormInterface::class);
        $form->method('getData')->willReturn($adminUser);

        $resolver = new OptionsResolver();
        // What Sylius' UserType declares: a user with no id yet is also validated in the create group.
        $resolver->setDefault(
            'validation_groups',
            static fn (FormInterface $form): array => $form->getData()?->getId() === null
                ? ['sylius', 'sylius_user_create']
                : ['sylius'],
        );

        $this->extension($adminPasswordLoginEnabled)->configureOptions($resolver);

        $validationGroups = $resolver->resolve([])['validation_groups'];
        self::assertIsCallable($validationGroups);

        /** @var list<string> $groups */
        $groups = $validationGroups($form);

        return $groups;
    }

    protected function extension(bool $adminPasswordLoginEnabled): AdminUserTypeExtension
    {
        // Answers per scope, so reading the customer toggle here instead of the admin one would show up.
        $checker = $this->createStub(PasswordLoginCheckerInterface::class);
        $checker->method('isEnabled')->willReturnCallback(
            static fn (SettingsScope $scope): bool => $scope === SettingsScope::ADMIN
                ? $adminPasswordLoginEnabled
                : true,
        );

        return new AdminUserTypeExtension($checker);
    }
}
