<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type\Settings;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Validator\Constraint\CidrList;

/**
 * @extends AbstractType<array<string, mixed>>
 */
class IpWhitelistSettingsType extends AbstractType implements IpWhitelistSettingsTypeInterface
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('enabled', CheckboxType::class, [
                'label' => 'three_brs.ui.security_settings.ip_whitelist.enabled',
                'required' => false,
                'label_attr' => ['class' => 'checkbox-switch'],
            ])
            ->add('global_cidrs', TextareaType::class, [
                'label' => 'three_brs.ui.security_settings.ip_whitelist.global_cidrs',
                'help' => 'three_brs.ui.security_settings.ip_whitelist.global_cidrs_help',
                'required' => false,
                'attr' => ['rows' => 6, 'placeholder' => "10.0.0.0/8\n192.168.1.1\n2001:db8::/32"],
                'constraints' => [new CidrList()],
            ])
        ;

        $builder->get('global_cidrs')->addModelTransformer(new CallbackTransformer(
            static function (mixed $value): string {
                if (!is_array($value)) {
                    return '';
                }
                $lines = [];
                foreach ($value as $item) {
                    if (is_string($item) && $item !== '') {
                        $lines[] = $item;
                    }
                }

                return implode("\n", $lines);
            },
            static function (mixed $value): array {
                if (!is_string($value) || $value === '') {
                    return [];
                }
                $items = [];
                foreach (preg_split('/\r\n|\r|\n/', $value) ?: [] as $line) {
                    $line = trim($line);
                    if ($line !== '') {
                        $items[] = $line;
                    }
                }

                return $items;
            },
        ));
    }

    public function getBlockPrefix(): string
    {
        return 'three_brs_ip_whitelist_settings';
    }
}
