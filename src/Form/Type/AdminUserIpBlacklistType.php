<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use ThreeBRS\EnterpriseSecurityBundle\Form\DataTransformer\CidrListDataTransformer;
use ThreeBRS\EnterpriseSecurityBundle\Validator\Constraint\CidrList;

/**
 * @extends AbstractType<array<string, mixed>>
 */
class AdminUserIpBlacklistType extends AbstractType implements AdminUserIpBlacklistTypeInterface
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('enabled', CheckboxType::class, [
                'label' => 'three_brs.ip_blacklist.admin.enabled',
                'required' => false,
                'label_attr' => ['class' => 'checkbox-switch'],
            ])
            ->add('cidrs', TextareaType::class, [
                'label' => 'three_brs.ip_blacklist.admin.cidrs',
                'help' => 'three_brs.ip_blacklist.admin.cidrs_help',
                'required' => false,
                'attr' => ['rows' => 6, 'placeholder' => "10.0.0.0/8\n192.168.1.1\n2001:db8::/32"],
                'constraints' => [new CidrList()],
            ])
        ;

        $builder->get('cidrs')->addModelTransformer(new CidrListDataTransformer());
    }

    public function getBlockPrefix(): string
    {
        return 'three_brs_admin_user_ip_blacklist';
    }
}
