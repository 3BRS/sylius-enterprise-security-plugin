<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service;

use ThreeBRS\EnterpriseSecurityBundle\MagicLink\MagicLinkTokenVerifierInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserMagicLinkTokenInterface;

interface AdminUserMagicLinkTokenVerifierInterface extends MagicLinkTokenVerifierInterface
{
    public function verify(string $plainToken): ?AdminUserMagicLinkTokenInterface;
}
