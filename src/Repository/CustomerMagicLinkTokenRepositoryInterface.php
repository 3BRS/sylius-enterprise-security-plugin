<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository;

use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerMagicLinkTokenInterface;

interface CustomerMagicLinkTokenRepositoryInterface
{
    public function findOneByTokenHash(string $tokenHash): ?CustomerMagicLinkTokenInterface;

    public function countRecentForShopUser(ShopUserInterface $user, \DateTimeImmutable $since): int;

    public function deleteExpiredBefore(\DateTimeImmutable $threshold): int;
}
