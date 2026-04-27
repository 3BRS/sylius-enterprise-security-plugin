<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener;

use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\LockableShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Lockout\ShopUserLockoutManagerInterface;

class ShopUserLoginAttemptListener implements ShopUserLoginAttemptListenerInterface
{
    /** @param CustomerRepositoryInterface<CustomerInterface> $customerRepository */
    public function __construct(
        protected CustomerRepositoryInterface $customerRepository,
        protected ShopUserLockoutManagerInterface $lockoutManager,
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

        $customer = $this->customerRepository->findOneBy(['email' => strtolower($identifier)]);
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
        $user = $event->getUser();
        if (!$user instanceof LockableShopUserInterface) {
            return;
        }

        $this->lockoutManager->recordSuccess($user);
    }
}
