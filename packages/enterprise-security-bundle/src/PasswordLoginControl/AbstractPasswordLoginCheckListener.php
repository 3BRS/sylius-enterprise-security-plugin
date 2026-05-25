<?php

declare(strict_types=1);

namespace ThreeBRS\EnterpriseSecurityBundle\PasswordLoginControl;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;

/**
 * Blocks a password-credentials login attempt when the resolved user has the per-user
 * password-login toggle disabled. OAuth / passkey / magic-link flows are untouched because
 * their passports do not carry a `PasswordCredentials` badge.
 *
 * Subclass binds the listener to a single user type (Customer vs AdminUser) — Symfony's
 * security event dispatchers fire per-firewall, but the same listener subscribes to all of
 * them, so the user-type filter is the only safe per-firewall narrowing.
 */
abstract class AbstractPasswordLoginCheckListener implements EventSubscriberInterface
{
    public function __construct(
        protected PasswordLoginPreferenceRepositoryInterface $preferenceRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Priority 256 puts the check before Symfony's default UserBadge / PasswordCredentials
        // resolution listener (priority 0) — we want to reject before the password hash is
        // verified to avoid leaking a timing side-channel on whether the password was correct.
        return [
            CheckPassportEvent::class => ['onCheckPassport', 256],
        ];
    }

    public function onCheckPassport(CheckPassportEvent $event): void
    {
        $passport = $event->getPassport();
        if (! $passport->hasBadge(PasswordCredentials::class)) {
            return;
        }

        $user = $passport->getUser();
        if (! $this->isAcceptableUser($user)) {
            return;
        }

        if ($this->preferenceRepository->isPasswordLoginAllowedForUser($user)) {
            return;
        }

        throw new CustomUserMessageAuthenticationException($this->getErrorMessageKey());
    }

    abstract protected function isAcceptableUser(UserInterface $user): bool;

    /**
     * Translation key thrown via CustomUserMessageAuthenticationException; rendered on the
     * login page by Symfony's authentication-utils + the firewall's `failure_path` template.
     */
    abstract protected function getErrorMessageKey(): string;
}
