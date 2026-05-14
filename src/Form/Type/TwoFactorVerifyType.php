<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

/** @extends AbstractType<array{code?: string}> */
class TwoFactorVerifyType extends AbstractType implements TwoFactorVerifyTypeInterface
{
    /** @param array<string, mixed> $options */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('code', TextType::class, [
            'label' => 'three_brs.ui.two_factor.code',
            'constraints' => [
                new NotBlank(message: 'three_brs.two_factor.code_required'),
                new Length(
                    min: 6,
                    max: 6,
                    exactMessage: 'three_brs.two_factor.invalid_code_length',
                ),
                new Regex(pattern: '/^\d{6}$/', message: 'three_brs.two_factor.invalid_code_format'),
            ],
            'attr' => [
                'autocomplete' => 'one-time-code',
                'inputmode' => 'numeric',
                'pattern' => '[0-9-]*',
                'maxlength' => 7,
                'placeholder' => 'XXX-XXX',
                'class' => 'form-control form-control-lg text-center font-monospace',
                'style' => 'letter-spacing: 0.25em; font-size: 1.75rem; font-weight: 600; width: 16rem; margin-left: auto; margin-right: auto; display: block;',
                'data-totp-code' => '',
            ],
        ]);

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
            $data = $event->getData();
            if (!is_array($data) || !isset($data['code']) || !is_string($data['code'])) {
                return;
            }
            $data['code'] = str_replace('-', '', $data['code']);
            $event->setData($data);
        });
    }

    public function getBlockPrefix(): string
    {
        return 'three_brs_two_factor_verify';
    }
}
