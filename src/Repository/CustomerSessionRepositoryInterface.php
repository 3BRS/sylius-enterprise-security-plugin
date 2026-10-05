<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository;

use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerSessionInterface;

interface CustomerSessionRepositoryInterface
{
    public function findOneBySessionId(string $sessionId): ?CustomerSessionInterface;

    /**
     * Sessions that are neither revoked nor expired, most recent activity first.
     *
     * @return list<CustomerSessionInterface>
     */
    public function findActiveForShopUser(ShopUserInterface $user): array;

    /**
     * Every session that has not been revoked, expired ones included. A session can still be
     * signed in after it expired: when the configured lifetime is shorter than the session handler
     * keeps sessions, or before PHP's session garbage collection removes it.
     *
     * @return list<CustomerSessionInterface>
     */
    public function findUnrevokedForShopUser(ShopUserInterface $user): array;

    /**
     * A session of the user that has not been revoked, expired or not.
     */
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
