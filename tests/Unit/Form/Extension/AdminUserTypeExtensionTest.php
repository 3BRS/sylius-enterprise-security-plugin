<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Form\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\FeatureToggleInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Extension\AdminUserTypeExtension;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\PasswordLoginControlAdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\LastAuthMethodGuardInterface;

/** @internal test double: admin user carrying the password-login flag */
interface TestPasswordControlAdminUser extends AdminUserInterface, PasswordLoginControlAdminUserInterface
{
}

#[CoversClass(AdminUserTypeExtension::class)]
class AdminUserTypeExtensionTest extends TestCase
{
    public function testAddsErrorWhenDisablingWouldLockTheAdminOut(): void
    {
        $admin = $this->createStub(TestPasswordControlAdminUser::class);
        $admin->method('isPasswordLoginAllowed')->willReturn(false);

        $guard = $this->createStub(LastAuthMethodGuardInterface::class);
        $guard->method('canDisablePasswordLoginForAdminUser')->willReturn(false);

        $field = $this->createMock(FormInterface::class);
        $field->expects(self::once())->method('addError')->with(self::isInstanceOf(FormError::class));

        $this->extension($guard)->guardAgainstLockout(new FormEvent($this->formWithField($field, hasField: true), $admin));
    }

    public function testNoErrorWhenTheAdminKeepsAnAlternativeSignInMethod(): void
    {
        $admin = $this->createStub(TestPasswordControlAdminUser::class);
        $admin->method('isPasswordLoginAllowed')->willReturn(false);

        $guard = $this->createStub(LastAuthMethodGuardInterface::class);
        $guard->method('canDisablePasswordLoginForAdminUser')->willReturn(true);

        $field = $this->createMock(FormInterface::class);
        $field->expects(self::never())->method('addError');

        $this->extension($guard)->guardAgainstLockout(new FormEvent($this->formWithField($field, hasField: true), $admin));
    }

    public function testNoErrorWhenPasswordLoginStaysAllowed(): void
    {
        $admin = $this->createStub(TestPasswordControlAdminUser::class);
        $admin->method('isPasswordLoginAllowed')->willReturn(true);

        $guard = $this->createMock(LastAuthMethodGuardInterface::class);
        $guard->expects(self::never())->method('canDisablePasswordLoginForAdminUser');

        $field = $this->createMock(FormInterface::class);
        $field->expects(self::never())->method('addError');

        $this->extension($guard)->guardAgainstLockout(new FormEvent($this->formWithField($field, hasField: true), $admin));
    }

    public function testDoesNothingWhenTheFormHasNoPasswordLoginField(): void
    {
        $field = $this->createMock(FormInterface::class);
        $field->expects(self::never())->method('addError');

        $this->extension()->guardAgainstLockout(
            new FormEvent($this->formWithField($field, hasField: false), $this->createStub(TestPasswordControlAdminUser::class)),
        );
    }

    public function testDoesNothingForNonAdminData(): void
    {
        $field = $this->createMock(FormInterface::class);
        $field->expects(self::never())->method('addError');

        $this->extension()->guardAgainstLockout(
            new FormEvent($this->formWithField($field, hasField: true), $this->createStub(UserInterface::class)),
        );
    }

    protected function formWithField(FormInterface $field, bool $hasField): FormInterface
    {
        $form = $this->createStub(FormInterface::class);
        $form->method('has')->willReturn($hasField);
        $form->method('get')->willReturn($field);

        return $form;
    }

    protected function extension(?LastAuthMethodGuardInterface $guard = null): AdminUserTypeExtension
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturn('Password login cannot be disabled — no other sign-in method.');

        return new AdminUserTypeExtension(
            $guard ?? $this->createStub(LastAuthMethodGuardInterface::class),
            $this->createStub(FeatureToggleInterface::class),
            $translator,
        );
    }
}
