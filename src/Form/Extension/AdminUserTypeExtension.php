<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Extension;

use Sylius\Bundle\AdminBundle\Form\Type\AdminUserType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use ThreeBRS\EnterpriseSecurityBundle\PasswordExpiration\PasswordExpirationAdminUserInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\PasswordLoginCheckerInterface;

class AdminUserTypeExtension extends AbstractTypeExtension implements AdminUserTypeExtensionInterface
{
    /** Sylius adds this group for users that have no id yet; it carries the NotBlank on plainPassword. */
    protected const CREATE_VALIDATION_GROUP = 'sylius_user_create';

    public function __construct(
        protected PasswordLoginCheckerInterface $passwordLoginChecker,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // When password login is disabled for the admin scope nobody signs in with a
        // password, so the password field must not be set or edited for anyone. This holds
        // regardless of the password-expiration trait, so it runs before the interface gate
        // below — otherwise an AdminUser without that trait would keep a working password field.
        if (!$this->passwordLoginChecker->isEnabled(SettingsScope::ADMIN)) {
            if ($builder->has('plainPassword')) {
                $builder->remove('plainPassword');
            }

            return;
        }

        $dataClass = $options['data_class'] ?? null;
        if ($dataClass !== null && is_a($dataClass, PasswordExpirationAdminUserInterface::class, true)) {
            $builder->add('forcePasswordChange', CheckboxType::class, [
                'label' => 'three_brs.form.admin_user.force_password_change',
                'required' => false,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->addNormalizer(
            'validation_groups',
            function (Options $options, mixed $value): mixed {
                if ($this->passwordLoginChecker->isEnabled(SettingsScope::ADMIN)) {
                    return $value;
                }

                // Sylius validates a new user in the "sylius_user_create" group, which requires a plain
                // password. With password login disabled that field does not exist, so the constraint
                // would reject every new administrator with an error nobody can act on. Administrators
                // created this way have no password and sign in with a magic link or a connected social
                // account — the same shape of account an OAuth sign-up creates.
                $createGroup = static::CREATE_VALIDATION_GROUP;

                return static function (FormInterface $form) use ($value, $createGroup): mixed {
                    $groups = $value instanceof \Closure ? $value($form) : $value;
                    if (!is_array($groups)) {
                        return $groups;
                    }

                    return array_values(array_filter(
                        $groups,
                        static fn (mixed $group): bool => $group !== $createGroup,
                    ));
                };
            },
        );
    }

    public static function getExtendedTypes(): iterable
    {
        yield AdminUserType::class;
    }
}
