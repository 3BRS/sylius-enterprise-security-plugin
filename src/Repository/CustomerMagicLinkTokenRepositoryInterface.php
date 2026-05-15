<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository;

use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerMagicLinkTokenInterface;

interface CustomerMagicLinkTokenRepositoryInterface
{
    public function findOneByTokenHash(string $tokenHash): ?CustomerMagicLinkTokenInterface;
}
