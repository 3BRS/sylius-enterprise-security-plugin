<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service;

use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserMagicLinkTokenInterface;

interface AdminUserMagicLinkTokenVerifierInterface
{
    public function verify(string $plainToken): ?AdminUserMagicLinkTokenInterface;
}
