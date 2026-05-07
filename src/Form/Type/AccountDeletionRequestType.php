<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @extends AbstractType<array<string, mixed>>
 */
class AccountDeletionRequestType extends AbstractType implements AccountDeletionRequestTypeInterface
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('currentPassword', PasswordType::class, [
                'label' => 'three_brs.ui.account_deletion.current_password',
                'mapped' => false,
                'constraints' => [
                    new NotBlank(message: 'three_brs.account_deletion.password_required'),
                ],
            ])
            ->add('acknowledged', CheckboxType::class, [
                'label' => 'three_brs.ui.account_deletion.acknowledge',
                'mapped' => false,
                'constraints' => [
                    new IsTrue(message: 'three_brs.account_deletion.acknowledge_required'),
                ],
            ])
        ;
    }

    public function getBlockPrefix(): string
    {
        return 'three_brs_account_deletion_request';
    }
}
