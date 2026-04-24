<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service;

use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerMagicLinkTokenInterface;

interface CustomerMagicLinkTokenVerifierInterface
{
    public function verify(string $plainToken): ?CustomerMagicLinkTokenInterface;
}
