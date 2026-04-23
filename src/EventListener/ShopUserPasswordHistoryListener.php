<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener;

use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerPasswordHistory;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerPasswordHistoryRepositoryInterface;

/**
 * @extends AbstractPasswordHistoryListener<ShopUserInterface>
 */
class ShopUserPasswordHistoryListener extends AbstractPasswordHistoryListener implements ShopUserPasswordHistoryListenerInterface
{
    public function __construct(
        private CustomerPasswordHistoryRepositoryInterface $repository,
        bool $enabled,
        int $count,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($enabled, $count, $logger);
    }

    protected function supports(object $entity): bool
    {
        return $entity instanceof ShopUserInterface;
    }

    protected function getPassword(object $user): ?string
    {
        return $user->getPassword();
    }

    protected function createHistoryEntry(object $user): object
    {
        $entry = new CustomerPasswordHistory();
        $entry->setShopUser($user);
        $entry->setPasswordHash((string) $user->getPassword());

        return $entry;
    }

    protected function deleteOldEntries(object $user, int $keepCount): void
    {
        $this->repository->deleteOldEntriesForShopUser($user, $keepCount);
    }
}
