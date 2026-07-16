<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener;

use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use ThreeBRS\EnterpriseSecurityBundle\PasswordLoginControl\AbstractPasswordLoginCheckListener;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\PasswordLoginCheckerInterface;

class CustomerPasswordLoginCheckListener extends AbstractPasswordLoginCheckListener implements CustomerPasswordLoginCheckListenerInterface
{
    /** Every entry point the web shop authenticates a password through. */
    protected const WEB_LOGIN_CHECK_ROUTES = [
        // The login page (form_login).
        'sylius_shop_login_check',
        // The inline sign-in of the checkout address step (json_login) — it lives on the shop
        // firewall, not on the API one, so it is web and has to obey the toggle as well.
        'sylius_shop_json_login_check',
    ];

    public function __construct(
        protected PasswordLoginCheckerInterface $passwordLoginChecker,
        protected RequestStack $requestStack,
    ) {
    }

    public function onCheckPassport(CheckPassportEvent $event): void
    {
        // Enforce on the web login checks only. The API authenticates on its own routes
        // (/api/v2/...), which are absent from this list on purpose: the plugin never gates the
        // API, it behaves there as if it were not installed.
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null || !in_array($request->attributes->get('_route'), static::WEB_LOGIN_CHECK_ROUTES, true)) {
            return;
        }

        parent::onCheckPassport($event);
    }

    protected function isPasswordLoginEnabled(): bool
    {
        return $this->passwordLoginChecker->isEnabled(SettingsScope::CUSTOMER);
    }

    protected function isAcceptableUser(UserInterface $user): bool
    {
        return $user instanceof ShopUserInterface;
    }

    protected function getErrorMessageKey(): string
    {
        return 'three_brs.password_login.disabled';
    }
}
