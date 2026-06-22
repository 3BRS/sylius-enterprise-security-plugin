<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Menu;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\FeatureToggleInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;

class ShopAccountMenuListener implements ShopAccountMenuListenerInterface
{
    public function __construct(
        protected FeatureToggleInterface $features,
        protected TokenStorageInterface $tokenStorage,
        protected RouterInterface $router,
    ) {
    }

    public function addTwoFactorItem(MenuBuilderEvent $event): void
    {
        if (!$this->features->isTwoFactorActive(SettingsScope::CUSTOMER)) {
            return;
        }

        $menu = $event->getMenu();

        $menu
            ->addChild('two_factor_authentication', ['route' => 'three_brs_shop_two_factor_setup'])
            ->setLabel('three_brs.ui.two_factor.menu_item')
            ->setLabelAttribute('icon', 'tabler:shield-lock')
        ;
    }

    public function addSocialAccountsItem(MenuBuilderEvent $event): void
    {
        if (
            !$this->features->isEnabled('oauth.google', SettingsScope::CUSTOMER) &&
            !$this->features->isEnabled('oauth.apple', SettingsScope::CUSTOMER)
        ) {
            return;
        }

        $menu = $event->getMenu();

        $menu
            ->addChild('social_accounts', ['route' => 'three_brs_shop_social_accounts'])
            ->setLabel('three_brs.ui.social_login.menu_item')
            ->setLabelAttribute('icon', 'tabler:user-circle')
        ;
    }

    public function addPasskeyItem(MenuBuilderEvent $event): void
    {
        if (!$this->features->isEnabled('passkey', SettingsScope::CUSTOMER)) {
            return;
        }

        $menu = $event->getMenu();

        $menu
            ->addChild('passkey', ['route' => 'three_brs_shop_passkey_index'])
            ->setLabel('three_brs.ui.passkey.menu_item')
            ->setLabelAttribute('icon', 'tabler:fingerprint')
        ;
    }

    public function addSessionsItem(MenuBuilderEvent $event): void
    {
        if (!$this->features->isEnabled('session_management', SettingsScope::CUSTOMER)) {
            return;
        }

        $menu = $event->getMenu();

        $menu
            ->addChild('sessions', ['route' => 'three_brs_shop_sessions'])
            ->setLabel('three_brs.ui.session.menu_item')
            ->setLabelAttribute('icon', 'tabler:devices')
        ;
    }

    public function addAccountDeletionItem(MenuBuilderEvent $event): void
    {
        if (!$this->features->isEnabled('account_deletion', SettingsScope::CUSTOMER)) {
            return;
        }

        $menu = $event->getMenu();

        $menu
            ->addChild('account_deletion', ['route' => 'three_brs_shop_account_deletion_request'])
            ->setLabel('three_brs.ui.account_deletion.menu_item')
            ->setLabelAttribute('icon', 'tabler:user-x')
        ;
    }

    public function swapChangePasswordItem(MenuBuilderEvent $event): void
    {
        $user = $this->tokenStorage->getToken()?->getUser();

        if (!$user instanceof ShopUserInterface) {
            return;
        }

        // Only customers without a password yet (OAuth / magic link / passkey)
        // get the "Set a new password" entry; everyone else keeps the standard
        // Sylius change-password item, which requires the current password.
        $password = $user->getPassword();
        if ($password !== null && $password !== '') {
            return;
        }

        $item = $event->getMenu()->getChild('change_password');
        if ($item === null) {
            return;
        }

        // The core item already carries the 'tabler:lock' icon, so only the
        // destination and label need to change here.
        $item
            ->setUri($this->router->generate('three_brs_shop_set_password'))
            ->setLabel('three_brs.ui.set_password.menu_item')
        ;
    }
}
