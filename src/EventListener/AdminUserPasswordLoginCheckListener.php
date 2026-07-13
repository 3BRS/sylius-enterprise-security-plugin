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
    protected const WEB_LOGIN_CHECK_ROUTE = 'sylius_admin_login_check';

    public function __construct(
        protected PasswordLoginCheckerInterface $passwordLoginChecker,
        protected RequestStack $requestStack,
    ) {
    }

    public function onCheckPassport(CheckPassportEvent $event): void
    {
        // Enforce only on the web form-login check; json_login / API password auth is left
        // untouched (the plugin never gates the API).
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null || $request->attributes->get('_route') !== static::WEB_LOGIN_CHECK_ROUTE) {
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
