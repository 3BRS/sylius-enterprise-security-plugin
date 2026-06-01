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
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @extends AbstractType<array<string, mixed>>
 */
class OAuthAutoRegistrationPolicySettingsType extends AbstractType implements OAuthAutoRegistrationPolicySettingsTypeInterface
{
    /** Upper bound on allow-listed domains — keeps the stored list, its validation and the textarea render bounded. */
    protected const MAX_DOMAINS = 100;

    /** Maximum length of a single domain name (RFC 1035). */
    protected const MAX_DOMAIN_LENGTH = 253;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var string $prefix */
        $prefix = $options['translation_prefix'];
        /** @var bool $includeDefaultLocale */
        $includeDefaultLocale = $options['include_default_locale'];

        if ($includeDefaultLocale) {
            $builder->add('default_locale', TextType::class, [
                'label' => $prefix . '.default_locale',
                'required' => true,
                'constraints' => [new NotBlank()],
                'help' => $prefix . '.default_locale_help',
            ]);
        }

        $builder->add('auto_register_allowed_email_domains', TextareaType::class, [
            'label' => $prefix . '.auto_register_allowed_email_domains',
            'help' => $prefix . '.auto_register_allowed_email_domains_help',
            'required' => false,
            'attr' => ['rows' => 4],
        ]);

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

        // Cap the list size and enforce a valid, RFC-bounded shape on each entry.
        // Errors are added to the field (not the compound form) so they surface
        // as a field error instead of bubbling to the form root. Runs on
        // POST_SUBMIT, after the transformer has reduced the textarea to a list.
        $builder->get('auto_register_allowed_email_domains')->addEventListener(
            FormEvents::POST_SUBMIT,
            static function (FormEvent $event): void {
                // On POST_SUBMIT, $event->getData() is the field's *view* data
                // (the raw textarea string). Read the field's model data instead
                // — the model transformer has reduced it to a list of domains.
                $data = $event->getForm()->getData();
                if (!is_array($data)) {
                    return;
                }

                if (count($data) > self::MAX_DOMAINS) {
                    $event->getForm()->addError(new FormError(
                        'three_brs.oauth_policy.too_many_domains',
                        'three_brs.oauth_policy.too_many_domains',
                        ['{{ limit }}' => self::MAX_DOMAINS],
                    ));

                    return;
                }

                $pattern = '/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i';
                foreach ($data as $domain) {
                    if (!is_string($domain) || strlen($domain) > self::MAX_DOMAIN_LENGTH || preg_match($pattern, $domain) !== 1) {
                        $event->getForm()->addError(new FormError('three_brs.oauth_policy.invalid_domain'));

                        return;
                    }
                }
            },
        );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('translation_prefix');
        $resolver->setAllowedTypes('translation_prefix', 'string');
        $resolver->setDefault('include_default_locale', true);
        $resolver->setAllowedTypes('include_default_locale', 'bool');
    }

    public function getBlockPrefix(): string
    {
        return 'three_brs_oauth_auto_registration_policy_settings';
    }
}
