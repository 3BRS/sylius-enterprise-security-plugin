<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type\Settings;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Positive;

/**
 * @extends AbstractType<array<string, mixed>>
 */
class PasswordHistorySettingsType extends AbstractType implements PasswordHistorySettingsTypeInterface
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('enabled', CheckboxType::class, [
                'label' => 'three_brs.ui.security_settings.password_history.enabled',
                'required' => false,
                'label_attr' => ['class' => 'checkbox-switch'],
            ])
            ->add('count', IntegerType::class, [
                'label' => 'three_brs.ui.security_settings.password_history.count',
                'required' => true,
                'constraints' => [new Positive()],
            ])
        ;
    }

    public function getBlockPrefix(): string
    {
        return 'three_brs_password_history_settings';
    }
}
