<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use ThreeBRS\EnterpriseSecurityBundle\PasswordPolicy\Constraint\PasswordPolicy;

/**
 * @extends AbstractType<array<string, mixed>>
 */
class SetPasswordType extends AbstractType implements SetPasswordTypeInterface
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Mirrors the "new password" part of Sylius' change-password form, but
        // without the "current password" field — for users that do not have a
        // password yet (registered via OAuth, magic link or passkey).
        $builder->add('newPassword', RepeatedType::class, [
            'type' => PasswordType::class,
            'first_options' => [
                'label' => 'sylius.form.user_change_password.new',
                'attr' => ['autocomplete' => 'new-password'],
            ],
            'second_options' => [
                'label' => 'sylius.form.user_change_password.confirmation',
                'attr' => ['autocomplete' => 'new-password'],
            ],
            'invalid_message' => 'sylius.user.plainPassword.mismatch',
            'constraints' => [
                new NotBlank(['message' => 'sylius.user.plainPassword.not_blank']),
                new PasswordPolicy(['policyGroup' => 'customer']),
            ],
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'three_brs_set_password';
    }
}
