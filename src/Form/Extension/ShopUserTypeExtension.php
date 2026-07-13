<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Extension;

use Sylius\Bundle\AdminBundle\Form\Type\ShopUserType;
use Sylius\Bundle\CoreBundle\Form\Type\User\ShopUserType as BaseShopUserType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\PasswordLoginCheckerInterface;

class ShopUserTypeExtension extends AbstractTypeExtension implements ShopUserTypeExtensionInterface
{
    public function __construct(
        protected PasswordLoginCheckerInterface $passwordLoginChecker,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // When password login is disabled for the customer scope, customers cannot sign in
        // with a password, so the password field must not be set or edited for anyone in the
        // admin panel. ShopUserType is the admin-side type nested under the customer form.
        if (!$this->passwordLoginChecker->isEnabled(SettingsScope::CUSTOMER) && $builder->has('plainPassword')) {
            $builder->remove('plainPassword');
        }
    }

    public static function getExtendedTypes(): iterable
    {
        // The base type has to be covered too: while handling the submit of the customer form Sylius
        // swaps the admin type for the base one (AddUserFormSubscriber), so without this the password
        // field would reappear as soon as that form is re-rendered.
        yield BaseShopUserType::class;

        yield ShopUserType::class;
    }
}
