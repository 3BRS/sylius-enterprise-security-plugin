<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type\Settings;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings\SecuritySettingsBounds;

/**
 * @extends AbstractType<array<string, mixed>>
 */
class AccountDeletionSettingsType extends AbstractType implements AccountDeletionSettingsTypeInterface
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('enabled', CheckboxType::class, [
                'label' => 'three_brs.ui.security_settings.account_deletion.enabled',
                'required' => false,
                'label_attr' => ['class' => 'checkbox-switch'],
            ])
            ->add('grace_period_days', IntegerType::class, [
                'label' => 'three_brs.ui.security_settings.account_deletion.grace_period_days',
                'help' => 'three_brs.ui.security_settings.account_deletion.grace_period_days_help',
                'required' => true,
                'attr' => ['min' => 1, 'max' => SecuritySettingsBounds::ACCOUNT_DELETION_GRACE_PERIOD_DAYS_MAX],
                'constraints' => [new NotBlank(), new Range(min: 1, max: SecuritySettingsBounds::ACCOUNT_DELETION_GRACE_PERIOD_DAYS_MAX)],
            ])
        ;
    }

    public function getBlockPrefix(): string
    {
        return 'three_brs_account_deletion_settings';
    }
}
