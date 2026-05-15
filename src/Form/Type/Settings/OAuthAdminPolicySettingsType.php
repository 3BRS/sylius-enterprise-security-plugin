<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type\Settings;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @extends AbstractType<array<string, mixed>>
 */
class OAuthAdminPolicySettingsType extends AbstractType implements OAuthAdminPolicySettingsTypeInterface
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('default_locale', TextType::class, [
                'label' => 'three_brs.ui.security_settings.oauth_admin_policy.default_locale',
                'required' => true,
                'constraints' => [new NotBlank()],
                'help' => 'three_brs.ui.security_settings.oauth_admin_policy.default_locale_help',
            ])
            ->add('auto_register_allowed_email_domains', TextareaType::class, [
                'label' => 'three_brs.ui.security_settings.oauth_admin_policy.auto_register_allowed_email_domains',
                'help' => 'three_brs.ui.security_settings.oauth_admin_policy.auto_register_allowed_email_domains_help',
                'required' => false,
                'attr' => ['rows' => 4],
            ])
        ;

        $builder->get('auto_register_allowed_email_domains')->addModelTransformer(new CallbackTransformer(
            // model → view: list<string> → newline-joined text
            static fn (mixed $value): string => is_array($value) ? implode("\n", $value) : '',
            // view → model: newline-separated text → list<string>
            static function (mixed $value): array {
                if (!is_string($value)) {
                    return [];
                }
                $lines = preg_split('/[\r\n]+/', $value);
                if ($lines === false) {
                    return [];
                }
                $result = [];
                foreach ($lines as $line) {
                    $trimmed = trim($line);
                    if ($trimmed !== '') {
                        $result[] = $trimmed;
                    }
                }

                return $result;
            },
        ));

        // Per-row domain shape check. The model transformer reduces the textarea
        // to a list of strings; we then enforce the shape on each entry. We run
        // this on POST_SUBMIT (after the transformer) because a regular `Regex`
        // constraint on the parent field would only match the whole multi-line
        // blob, not each domain individually.
        $builder->get('auto_register_allowed_email_domains')->addEventListener(
            FormEvents::POST_SUBMIT,
            static function (FormEvent $event): void {
                $data = $event->getData();
                if (!is_array($data)) {
                    return;
                }
                $pattern = '/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i';
                foreach ($data as $domain) {
                    if (!is_string($domain) || preg_match($pattern, $domain) !== 1) {
                        $event->getForm()->addError(new FormError(
                            'three_brs.ui.security_settings.oauth_admin_policy.invalid_domain',
                        ));

                        return;
                    }
                }
            },
        );
    }

    public function getBlockPrefix(): string
    {
        return 'three_brs_oauth_admin_policy_settings';
    }
}
