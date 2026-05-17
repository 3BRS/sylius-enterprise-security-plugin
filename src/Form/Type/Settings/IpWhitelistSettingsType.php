<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type\Settings;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use ThreeBRS\EnterpriseSecurityBundle\Form\DataTransformer\CidrListDataTransformer;
use ThreeBRS\EnterpriseSecurityBundle\Validator\Constraint\CidrList;

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

        $builder->get('global_cidrs')->addModelTransformer(new CidrListDataTransformer());

        // Cross-field guard: enabling the whitelist with an empty global list
        // and no per-admin override locks the saving admin out on the next
        // request (and every other admin too). We can't check the per-admin
        // table from here without DB access, so we err on the safe side and
        // require at least one global CIDR whenever the feature is enabled —
        // an admin who really wants pure per-admin mode can add `0.0.0.0/0`
        // (and IPv6 `::/0`) to the global list as an explicit acknowledgement.
        // The error is attached to the `global_cidrs` field (not the root) so
        // SaveTabController's CSRF detection — which treats root errors as
        // expired-session — doesn't misclassify this as a CSRF mismatch.
        $builder->addEventListener(FormEvents::POST_SUBMIT, static function (FormEvent $event): void {
            $data = $event->getData();
            if (!is_array($data)) {
                return;
            }
            $enabled = (bool) ($data['enabled'] ?? false);
            $cidrs = $data['global_cidrs'] ?? null;
            $hasCidrs = is_array($cidrs) && $cidrs !== [];
            if ($enabled && !$hasCidrs) {
                $event->getForm()->get('global_cidrs')->addError(new FormError(
                    'three_brs.ip_whitelist.enabled_requires_cidrs',
                ));
            }
        });
    }

    public function getBlockPrefix(): string
    {
        return 'three_brs_ip_whitelist_settings';
    }
}
