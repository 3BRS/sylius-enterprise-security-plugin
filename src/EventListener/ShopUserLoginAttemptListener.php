<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener;

use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use ThreeBRS\EnterpriseSecurityBundle\Lockout\LockableShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Lockout\ShopUserLockoutManagerInterface;

class ShopUserLoginAttemptListener implements ShopUserLoginAttemptListenerInterface
{
    /** @param CustomerRepositoryInterface<CustomerInterface> $customerRepository */
    public function __construct(
        protected CustomerRepositoryInterface $customerRepository,
        protected ShopUserLockoutManagerInterface $lockoutManager,
        protected string $apiRoute,
    ) {
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        // The API authenticates statelessly (JWT) per request and must behave as
        // if this plugin were not installed — never let API logins affect lockout
        // state, or an API client could lock an account out of the web panel.
        if ($this->apiRoute !== '' && str_starts_with($event->getRequest()->getPathInfo(), $this->apiRoute)) {
            return;
        }

        $passport = $event->getPassport();
        if ($passport === null) {
            return;
        }

        $identifier = (string) $passport->getBadge(UserBadge::class)?->getUserIdentifier();
        if ($identifier === '') {
            return;
        }

        // Sylius shop login is email-only: the username field on ShopUser is
        // auto-populated from the customer's email by Sylius's
        // DefaultUsernameORMListener, so we always look up by customer email.
        $customer = $this->customerRepository->findOneBy(['emailCanonical' => strtolower($identifier)]);
        if (!$customer instanceof CustomerInterface) {
            return;
        }

        $shopUser = $customer->getUser();
        if (!$shopUser instanceof ShopUserInterface || !$shopUser instanceof LockableShopUserInterface) {
            return;
        }

        $this->lockoutManager->recordFailure($shopUser);
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        // Same API exemption as onLoginFailure — see above.
        if ($this->apiRoute !== '' && str_starts_with($event->getRequest()->getPathInfo(), $this->apiRoute)) {
            return;
        }

        $user = $event->getUser();
        if (!$user instanceof LockableShopUserInterface) {
            return;
        }

        $this->lockoutManager->recordSuccess($user);
    }
}
