<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

/** @extends AbstractType<array{code?: string}> */
class TwoFactorVerifyType extends AbstractType
{
    /** @param array<string, mixed> $options */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('code', TextType::class, [
            'label' => 'three_brs.ui.two_factor.code',
            'constraints' => [
                new NotBlank(),
                new Length(min: 6, max: 8),
                new Regex(pattern: '/^\d+$/', message: 'three_brs.two_factor.invalid_code_format'),
            ],
            'attr' => [
                'autocomplete' => 'one-time-code',
                'inputmode' => 'numeric',
                'pattern' => '[0-9]*',
            ],
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'three_brs_two_factor_verify';
    }
}
