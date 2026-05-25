<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository;

use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerSessionInterface;

interface CustomerSessionRepositoryInterface
{
    public function findOneBySessionId(string $sessionId): ?CustomerSessionInterface;

    /** @return list<CustomerSessionInterface> */
    public function findActiveForShopUser(ShopUserInterface $user): array;

    public function findActiveByIdForShopUser(int $id, ShopUserInterface $user): ?CustomerSessionInterface;

    /**
     * History of all sessions for the user — active and revoked — ordered by createdAt DESC.
     * Used to render a customer's login history in the admin panel.
     *
     * @return list<CustomerSessionInterface>
     */
    public function findAllForShopUser(ShopUserInterface $user, int $limit = 50): array;

    public function findByIdAndShopUser(int $id, ShopUserInterface $user): ?CustomerSessionInterface;
}
