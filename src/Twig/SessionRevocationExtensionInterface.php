<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Twig;

use Symfony\Component\Security\Core\User\UserInterface;

interface SessionRevocationExtensionInterface
{
    /**
     * Whether a session of the user other than the current one has not been revoked, expired or not.
     */
    public function hasOtherUnrevokedSessions(?UserInterface $user, string $currentSessionId): bool;
}
