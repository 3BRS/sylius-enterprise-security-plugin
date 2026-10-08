<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session;

interface SessionLifetimeInterface
{
    /**
     * A session whose last recorded activity is not newer than this has expired.
     */
    public function getActiveSince(): \DateTimeImmutable;
}
