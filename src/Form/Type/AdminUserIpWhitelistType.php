<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Validator\Constraint\CidrList;

/**
 * @extends AbstractType<array<string, mixed>>
 */
class AdminUserIpWhitelistType extends AbstractType implements AdminUserIpWhitelistTypeInterface
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('enabled', CheckboxType::class, [
                'label' => 'three_brs.ip_whitelist.admin.enabled',
                'required' => false,
                'label_attr' => ['class' => 'checkbox-switch'],
            ])
            ->add('cidrs', TextareaType::class, [
                'label' => 'three_brs.ip_whitelist.admin.cidrs',
                'help' => 'three_brs.ip_whitelist.admin.cidrs_help',
                'required' => false,
                'attr' => ['rows' => 6, 'placeholder' => "10.0.0.0/8\n192.168.1.1\n2001:db8::/32"],
                'constraints' => [new CidrList()],
            ])
        ;

        $builder->get('cidrs')->addModelTransformer(new CallbackTransformer(
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

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'three_brs_admin_user_ip_whitelist';
    }
}
