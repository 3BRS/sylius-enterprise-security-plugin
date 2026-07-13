<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Extension;

use Sylius\Bundle\AdminBundle\Form\Type\CustomerType;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\PasswordLoginCheckerInterface;

class CustomerTypeExtension extends AbstractTypeExtension implements CustomerTypeExtensionInterface
{
    /** Runs before Sylius' AddUserFormSubscriber, which is registered with the default priority. */
    protected const CAPTURE_PRIORITY = 1;

    /** Runs after it. */
    protected const RESTORE_PRIORITY = -1;

    public function __construct(
        protected PasswordLoginCheckerInterface $passwordLoginChecker,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($this->passwordLoginChecker->isEnabled(SettingsScope::CUSTOMER)) {
            return;
        }

        // Sylius takes the password as the signal that the admin wants an account for this customer:
        // AddUserFormSubscriber throws away a brand-new shop user that has no plain password, which is
        // how a customer stays a guest. With password login disabled there is no password field at all,
        // so the "Enabled" checkbox is the only signal left. Keep the account Sylius is about to discard
        // and store it without a password — the same shape of account an OAuth sign-up creates. Without
        // this, ticking "Enabled" on a guest customer saves successfully but changes nothing.
        $pendingUser = null;

        $builder->addEventListener(
            FormEvents::SUBMIT,
            static function (FormEvent $event) use (&$pendingUser): void {
                $customer = $event->getData();
                if (!$customer instanceof CustomerInterface) {
                    return;
                }

                $user = $customer->getUser();
                if ($user instanceof ShopUserInterface && $user->getId() === null && $user->isEnabled()) {
                    $pendingUser = $user;
                }
            },
            static::CAPTURE_PRIORITY,
        );

        $builder->addEventListener(
            FormEvents::SUBMIT,
            static function (FormEvent $event) use (&$pendingUser): void {
                if (!$pendingUser instanceof ShopUserInterface) {
                    return;
                }

                $customer = $event->getData();
                if ($customer instanceof CustomerInterface && $customer->getUser() === null) {
                    $customer->setUser($pendingUser);
                }
            },
            static::RESTORE_PRIORITY,
        );
    }

    public static function getExtendedTypes(): iterable
    {
        yield CustomerType::class;
    }
}
