<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type\Settings;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;

/**
 * @extends AbstractType<array<string, mixed>>
 */
class MagicLinkSettingsType extends AbstractType implements MagicLinkSettingsTypeInterface
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('expiration_seconds', IntegerType::class, [
                'label' => 'three_brs.ui.security_settings.magic_link.expiration_seconds',
                'required' => true,
                'constraints' => [new GreaterThanOrEqual(60)],
            ])
            ->add('enabled', CheckboxType::class, [
                'label' => 'three_brs.ui.security_settings.magic_link.enabled',
                'required' => false,
                'label_attr' => ['class' => 'checkbox-switch'],
            ])
        ;
    }

    public function getBlockPrefix(): string
    {
        return 'three_brs_magic_link_settings';
    }
}
