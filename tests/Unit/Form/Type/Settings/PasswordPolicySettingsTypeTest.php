<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Form\Type\Settings;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormExtensionInterface;
use Symfony\Component\Form\Test\FormIntegrationTestCase;
use Symfony\Component\Validator\Validation;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type\Settings\PasswordPolicySettingsType;

#[CoversClass(PasswordPolicySettingsType::class)]
class PasswordPolicySettingsTypeTest extends FormIntegrationTestCase
{
    /**
     * Register the form `constraints` option (and run field-level Range
     * constraints) — not loaded by the bare TypeTestCase factory.
     *
     * @return list<FormExtensionInterface>
     */
    protected function getExtensions(): array
    {
        return [new ValidatorExtension(Validation::createValidator())];
    }

    public function testRejectsMaxLengthBelowMinLength(): void
    {
        $form = $this->factory->create(PasswordPolicySettingsType::class);
        $form->submit(['min_length' => '10', 'max_length' => '5']);

        $errors = $form->get('max_length')->getErrors();
        self::assertGreaterThan(0, $errors->count());
        self::assertSame('three_brs.password_policy.max_length_below_min', $errors[0]->getMessage());
    }

    public function testAcceptsMaxLengthAboveMinLength(): void
    {
        $form = $this->factory->create(PasswordPolicySettingsType::class);
        $form->submit(['min_length' => '8', 'max_length' => '20']);

        self::assertCount(0, $form->get('max_length')->getErrors());
    }

    public function testAcceptsEmptyMaxLength(): void
    {
        $form = $this->factory->create(PasswordPolicySettingsType::class);
        $form->submit(['min_length' => '8', 'max_length' => '']);

        self::assertCount(0, $form->get('max_length')->getErrors());
    }
}
