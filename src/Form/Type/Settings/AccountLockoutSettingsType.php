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
class AccountLockoutSettingsType extends AbstractType implements AccountLockoutSettingsTypeInterface
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('enabled', CheckboxType::class, [
                'label' => 'three_brs.ui.security_settings.account_lockout.enabled',
                'required' => false,
                'label_attr' => ['class' => 'checkbox-switch'],
            ])
            ->add('max_attempts', IntegerType::class, [
                'label' => 'three_brs.ui.security_settings.account_lockout.max_attempts',
                'required' => true,
                'attr' => ['min' => 1, 'max' => SecuritySettingsBounds::ACCOUNT_LOCKOUT_MAX_ATTEMPTS_MAX],
                'constraints' => [new NotBlank(), new Range(min: 1, max: SecuritySettingsBounds::ACCOUNT_LOCKOUT_MAX_ATTEMPTS_MAX)],
            ])
            ->add('auto_unlock_after', IntegerType::class, [
                'label' => 'three_brs.ui.security_settings.account_lockout.auto_unlock_after',
                'required' => false,
                'help' => 'three_brs.ui.security_settings.account_lockout.auto_unlock_after_help',
                'attr' => ['min' => 1, 'max' => SecuritySettingsBounds::ACCOUNT_LOCKOUT_AUTO_UNLOCK_AFTER_MAX],
                'constraints' => [new Range(min: 1, max: SecuritySettingsBounds::ACCOUNT_LOCKOUT_AUTO_UNLOCK_AFTER_MAX)],
            ])
        ;
    }

    public function getBlockPrefix(): string
    {
        return 'three_brs_account_lockout_settings';
    }
}
