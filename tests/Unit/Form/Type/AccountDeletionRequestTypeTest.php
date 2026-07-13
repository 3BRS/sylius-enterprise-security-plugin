<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Form\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type\AccountDeletionRequestType;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\PasswordLoginCheckerInterface;

#[CoversClass(AccountDeletionRequestType::class)]
class AccountDeletionRequestTypeTest extends TestCase
{
    public function testAsksForTheCurrentPasswordWhileCustomerPasswordLoginIsOn(): void
    {
        $added = $this->buildForm(customerPasswordLoginEnabled: true);

        self::assertSame(
            ['currentPassword' => PasswordType::class, 'acknowledged' => CheckboxType::class],
            $added,
        );
    }

    public function testConfirmsOnTheAcknowledgementAloneWhileCustomerPasswordLoginIsOff(): void
    {
        // A customer who signed up socially has no password at all — demanding one would leave them
        // unable to delete their own account.
        $added = $this->buildForm(customerPasswordLoginEnabled: false);

        self::assertSame(['acknowledged' => CheckboxType::class], $added);
    }

    /** @return array<string, string> field => form type */
    protected function buildForm(bool $customerPasswordLoginEnabled): array
    {
        $added = [];

        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(
            /** @param array<string, mixed> $options */
            static function (string $child, string $type, array $options) use ($builder, &$added): FormBuilderInterface {
                $added[$child] = $type;

                return $builder;
            },
        );

        // Answers per scope, so reading the admin toggle here instead of the customer one would show up.
        $checker = $this->createStub(PasswordLoginCheckerInterface::class);
        $checker->method('isEnabled')->willReturnCallback(
            static fn (SettingsScope $scope): bool => $scope === SettingsScope::CUSTOMER
                ? $customerPasswordLoginEnabled
                : true,
        );

        (new AccountDeletionRequestType($checker))->buildForm($builder, []);

        return $added;
    }
}
