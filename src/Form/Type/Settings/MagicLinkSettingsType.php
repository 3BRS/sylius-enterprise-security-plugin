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
class MagicLinkSettingsType extends AbstractType implements MagicLinkSettingsTypeInterface
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('enabled', CheckboxType::class, [
                'label' => 'three_brs.ui.security_settings.magic_link.enabled',
                'required' => false,
                'label_attr' => ['class' => 'checkbox-switch'],
            ])
            ->add('expiration_seconds', IntegerType::class, [
                'label' => 'three_brs.ui.security_settings.magic_link.expiration_seconds',
                'required' => true,
                'attr' => ['min' => SecuritySettingsBounds::MAGIC_LINK_EXPIRATION_SECONDS_MIN, 'max' => SecuritySettingsBounds::MAGIC_LINK_EXPIRATION_SECONDS_MAX],
                'constraints' => [new NotBlank(), new Range(min: SecuritySettingsBounds::MAGIC_LINK_EXPIRATION_SECONDS_MIN, max: SecuritySettingsBounds::MAGIC_LINK_EXPIRATION_SECONDS_MAX)],
            ])
        ;
    }

    public function getBlockPrefix(): string
    {
        return 'three_brs_magic_link_settings';
    }
}
