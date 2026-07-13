<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener;

use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use ThreeBRS\EnterpriseSecurityBundle\PasswordLoginControl\AbstractPasswordLoginCheckListener;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\PasswordLoginCheckerInterface;

class AdminUserPasswordLoginCheckListener extends AbstractPasswordLoginCheckListener implements AdminUserPasswordLoginCheckListenerInterface
{
    /** Every entry point the admin panel authenticates a password through. */
    protected const WEB_LOGIN_CHECK_ROUTES = [
        'sylius_admin_login_check',
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
        return $this->passwordLoginChecker->isEnabled(SettingsScope::ADMIN);
    }

    protected function isAcceptableUser(UserInterface $user): bool
    {
        return $user instanceof AdminUserInterface;
    }

    protected function getErrorMessageKey(): string
    {
        return 'three_brs.password_login.disabled';
    }
}
