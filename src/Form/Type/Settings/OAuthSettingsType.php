<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type\Settings;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * @extends AbstractType<array<string, mixed>>
 */
class OAuthSettingsType extends AbstractType implements OAuthSettingsTypeInterface
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('google_enabled', CheckboxType::class, [
                'label' => 'three_brs.ui.security_settings.oauth.google_enabled',
                'required' => false,
                'label_attr' => ['class' => 'checkbox-switch'],
            ])
            ->add('apple_enabled', CheckboxType::class, [
                'label' => 'three_brs.ui.security_settings.oauth.apple_enabled',
                'required' => false,
                'label_attr' => ['class' => 'checkbox-switch'],
            ])
            ->add('microsoft_enabled', CheckboxType::class, [
                'label' => 'three_brs.ui.security_settings.oauth.microsoft_enabled',
                'required' => false,
                'label_attr' => ['class' => 'checkbox-switch'],
            ])
        ;
    }

    public function getBlockPrefix(): string
    {
        return 'three_brs_oauth_settings';
    }
}
