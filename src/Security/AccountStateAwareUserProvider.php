<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Security;

use Sylius\Bundle\UserBundle\Provider\UserProviderInterface as SyliusUserProviderInterface;
use Sylius\Component\User\Model\UserInterface as SyliusUserInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Disabling an account stops the next sign-in, not the sessions already open.
 * Symfony reloads the user from the provider on every request, but the user
 * checkers that would refuse a disabled account run at authentication time, and
 * AbstractUserProvider::refreshUser() hands the row back whatever state it is
 * in, so a customer who asked to be erased — or one an administrator blocked —
 * keeps a working browser tab.
 *
 * The plugin revokes the tracked sessions on both paths, but session tracking is
 * off by default and there is nothing to revoke without it. Refusing the refresh
 * closes that on its own: Symfony's ContextListener answers UserNotFoundException
 * by dropping the token, so the next request from that tab is anonymous.
 *
 * Only stateful firewalls reach this. The API firewalls authenticate each
 * request from its own credential, where the user checker already refuses a
 * disabled account.
 */
class AccountStateAwareUserProvider implements AccountStateAwareUserProviderInterface
{
    public function __construct(
        protected SyliusUserProviderInterface $decorated,
    ) {
    }

    public function loadUserByUsername(mixed $username): UserInterface
    {
        return $this->decorated->loadUserByUsername($username);
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        return $this->decorated->loadUserByIdentifier($identifier);
    }

    public function supportsClass(mixed $class): bool
    {
        return $this->decorated->supportsClass($class);
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        $refreshedUser = $this->decorated->refreshUser($user);

        if ($refreshedUser instanceof SyliusUserInterface && !$refreshedUser->isEnabled()) {
            throw new UserNotFoundException(
                sprintf('User "%s" is disabled and its session is no longer valid.', $refreshedUser->getUserIdentifier()),
            );
        }

        return $refreshedUser;
    }
}
