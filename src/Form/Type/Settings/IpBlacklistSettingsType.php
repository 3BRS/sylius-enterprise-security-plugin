<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type\Settings;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use ThreeBRS\EnterpriseSecurityBundle\Form\DataTransformer\CidrListDataTransformer;
use ThreeBRS\EnterpriseSecurityBundle\Validator\Constraint\CidrList;

/**
 * @extends AbstractType<array<string, mixed>>
 */
class IpBlacklistSettingsType extends AbstractType implements IpBlacklistSettingsTypeInterface
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('enabled', CheckboxType::class, [
                'label' => 'three_brs.ui.security_settings.ip_blacklist.enabled',
                'required' => false,
                'label_attr' => ['class' => 'checkbox-switch'],
            ])
            ->add('global_cidrs', TextareaType::class, [
                'label' => 'three_brs.ui.security_settings.ip_blacklist.global_cidrs',
                'help' => 'three_brs.ui.security_settings.ip_blacklist.global_cidrs_help',
                'required' => false,
                'attr' => ['rows' => 6, 'placeholder' => "10.0.0.0/8\n192.168.1.1\n2001:db8::/32"],
                'constraints' => [new CidrList()],
            ])
        ;

        $builder->get('global_cidrs')->addModelTransformer(new CidrListDataTransformer());

        // No "enabled requires cidrs" guard for the blacklist — unlike the
        // whitelist (which is fail-closed and would lock everyone out when
        // toggled on without entries), the blacklist is fail-open: empty list
        // + enabled = no IPs blocked, which is harmless. An admin can flip the
        // toggle and populate the list later without locking anyone out.
    }

    public function getBlockPrefix(): string
    {
        return 'three_brs_ip_blacklist_settings';
    }
}
