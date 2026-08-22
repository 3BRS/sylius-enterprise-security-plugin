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
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Locale;
use Symfony\Component\Validator\Constraints\NotBlank;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings\SecuritySettingsBounds;

/**
 * @extends AbstractType<array<string, mixed>>
 */
class OAuthAutoRegistrationPolicySettingsType extends AbstractType implements OAuthAutoRegistrationPolicySettingsTypeInterface
{
    /** Matches the width of Sylius's `sylius_admin_user.locale_code` column. */
    protected const LOCALE_CODE_MAX_LENGTH = 12;

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
                // Written straight onto AdminUser.locale_code, a varchar(12): a longer
                // value reaches the driver as a "data too long" error in the middle of
                // an OAuth callback, where AbstractOAuthCallbackController catches only
                // OAuthProviderException. Length keeps it out of the column; Locale
                // keeps a well-formed-looking value from being a locale nobody has.
                'constraints' => [
                    new NotBlank(),
                    new Length(max: static::LOCALE_CODE_MAX_LENGTH),
                    new Locale(canonicalize: true),
                ],
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

                if (count($data) > SecuritySettingsBounds::OAUTH_ALLOWED_EMAIL_DOMAINS_MAX) {
                    $event->getForm()->addError(new FormError(
                        'three_brs.oauth_policy.too_many_domains',
                        'three_brs.oauth_policy.too_many_domains',
                        ['{{ limit }}' => SecuritySettingsBounds::OAUTH_ALLOWED_EMAIL_DOMAINS_MAX],
                    ));

                    return;
                }

                $pattern = '/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i';
                foreach ($data as $domain) {
                    if (!is_string($domain) || strlen($domain) > SecuritySettingsBounds::OAUTH_EMAIL_DOMAIN_LENGTH_MAX || preg_match($pattern, $domain) !== 1) {
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
