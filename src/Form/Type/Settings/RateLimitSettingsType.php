<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type\Settings;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;

/**
 * @extends AbstractType<array<string, mixed>>
 */
class RateLimitSettingsType extends AbstractType implements RateLimitSettingsTypeInterface
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var list<string> $actions */
        $actions = $options['actions'];

        foreach ($actions as $action) {
            $builder
                ->add($action . '_enabled', CheckboxType::class, [
                    'label' => 'three_brs.ui.security_settings.rate_limit.' . $action . '.enabled',
                    'required' => false,
                    'label_attr' => ['class' => 'checkbox-switch'],
                ])
                ->add($action . '_limit', IntegerType::class, [
                    'label' => 'three_brs.ui.security_settings.rate_limit.' . $action . '.limit',
                    'required' => true,
                    'constraints' => [new Positive()],
                ])
                ->add($action . '_interval', TextType::class, [
                    'label' => 'three_brs.ui.security_settings.rate_limit.' . $action . '.interval',
                    'help' => 'three_brs.ui.security_settings.rate_limit.interval_help',
                    'required' => true,
                    'constraints' => [new NotBlank()],
                ])
            ;
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'actions' => ['login', 'password_reset', 'register', 'magic_link'],
        ]);
        $resolver->setAllowedTypes('actions', 'array');
    }

    public function getBlockPrefix(): string
    {
        return 'three_brs_rate_limit_settings';
    }
}
