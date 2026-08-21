<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type\Settings;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings\SecuritySettingsBounds;

/**
 * @extends AbstractType<array<string, mixed>>
 */
class PasswordPolicySettingsType extends AbstractType implements PasswordPolicySettingsTypeInterface
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('require_uppercase', CheckboxType::class, [
                'label' => 'three_brs.ui.security_settings.password_policy.require_uppercase',
                'required' => false,
                'label_attr' => ['class' => 'checkbox-switch'],
            ])
            ->add('require_lowercase', CheckboxType::class, [
                'label' => 'three_brs.ui.security_settings.password_policy.require_lowercase',
                'required' => false,
                'label_attr' => ['class' => 'checkbox-switch'],
            ])
            ->add('require_numbers', CheckboxType::class, [
                'label' => 'three_brs.ui.security_settings.password_policy.require_numbers',
                'required' => false,
                'label_attr' => ['class' => 'checkbox-switch'],
            ])
            ->add('require_special_characters', CheckboxType::class, [
                'label' => 'three_brs.ui.security_settings.password_policy.require_special_characters',
                'required' => false,
                'label_attr' => ['class' => 'checkbox-switch'],
            ])
            ->add('min_length', IntegerType::class, [
                'label' => 'three_brs.ui.security_settings.password_policy.min_length',
                'required' => true,
                'attr' => ['min' => 1, 'max' => SecuritySettingsBounds::PASSWORD_POLICY_MIN_LENGTH_MAX],
                'constraints' => [new NotBlank(), new Range(min: 1, max: SecuritySettingsBounds::PASSWORD_POLICY_MIN_LENGTH_MAX)],
            ])
            ->add('max_length', IntegerType::class, [
                'label' => 'three_brs.ui.security_settings.password_policy.max_length',
                'required' => false,
                'attr' => ['min' => 1, 'max' => SecuritySettingsBounds::PASSWORD_POLICY_MAX_LENGTH_MAX],
                'constraints' => [new Range(min: 1, max: SecuritySettingsBounds::PASSWORD_POLICY_MAX_LENGTH_MAX)],
            ])
        ;

        // Cross-field guard: when both are set, max_length must not be below
        // min_length — otherwise no password could ever satisfy the policy. The
        // error is attached to the max_length field (not the compound form) so
        // it surfaces as a field error instead of bubbling to the form root.
        $builder->addEventListener(FormEvents::POST_SUBMIT, static function (FormEvent $event): void {
            $form = $event->getForm();
            $minLength = $form->get('min_length')->getData();
            $maxLength = $form->get('max_length')->getData();

            if (is_int($minLength) && is_int($maxLength) && $maxLength < $minLength) {
                $form->get('max_length')->addError(new FormError('three_brs.password_policy.max_length_below_min'));
            }
        });
    }

    public function getBlockPrefix(): string
    {
        return 'three_brs_password_policy_settings';
    }
}
