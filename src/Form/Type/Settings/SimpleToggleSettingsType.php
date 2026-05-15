<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type\Settings;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<array<string, mixed>>
 */
class SimpleToggleSettingsType extends AbstractType implements SimpleToggleSettingsTypeInterface
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('enabled', CheckboxType::class, [
                'label' => $options['label'],
                'required' => false,
                'label_attr' => ['class' => 'checkbox-switch'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('label');
        $resolver->setAllowedTypes('label', 'string');
    }

    public function getBlockPrefix(): string
    {
        return 'three_brs_simple_toggle_settings';
    }
}
