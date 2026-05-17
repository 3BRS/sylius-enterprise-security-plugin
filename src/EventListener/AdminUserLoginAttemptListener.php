<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener;

use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use ThreeBRS\EnterpriseSecurityBundle\Lockout\LockableAdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Lockout\AdminUserLockoutManagerInterface;

class AdminUserLoginAttemptListener implements AdminUserLoginAttemptListenerInterface
{
    /** @param UserRepositoryInterface<AdminUserInterface> $adminUserRepository */
    public function __construct(
        protected UserRepositoryInterface $adminUserRepository,
        protected AdminUserLockoutManagerInterface $lockoutManager,
    ) {
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $passport = $event->getPassport();
        if ($passport === null) {
            return;
        }

        $identifier = (string) $passport->getBadge(UserBadge::class)?->getUserIdentifier();
        if ($identifier === '') {
            return;
        }

        $user = $this->adminUserRepository->findOneBy(['usernameCanonical' => strtolower($identifier)])
            ?? $this->adminUserRepository->findOneBy(['emailCanonical' => strtolower($identifier)]);
        if (!$user instanceof LockableAdminUserInterface) {
            return;
        }

        $this->lockoutManager->recordFailure($user);
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof LockableAdminUserInterface) {
            return;
        }

        $this->lockoutManager->recordSuccess($user);
    }
}
