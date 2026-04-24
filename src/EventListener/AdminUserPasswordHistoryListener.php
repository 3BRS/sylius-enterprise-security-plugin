<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener;

use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserPasswordHistory;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserPasswordHistoryRepositoryInterface;

/**
 * @extends AbstractPasswordHistoryListener<AdminUserInterface>
 */
class AdminUserPasswordHistoryListener extends AbstractPasswordHistoryListener implements AdminUserPasswordHistoryListenerInterface
{
    public function __construct(
        private AdminUserPasswordHistoryRepositoryInterface $repository,
        bool $enabled,
        int $count,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($enabled, $count, $logger);
    }

    protected function supports(object $entity): bool
    {
        return $entity instanceof AdminUserInterface;
    }

    protected function getPassword(object $user): ?string
    {
        return $user->getPassword();
    }

    protected function createHistoryEntry(object $user): object
    {
        $entry = new AdminUserPasswordHistory();
        $entry->setAdminUser($user);
        $entry->setPasswordHash((string) $user->getPassword());

        return $entry;
    }

    protected function deleteOldEntries(object $user, int $keepCount): void
    {
        $this->repository->deleteOldEntriesForAdminUser($user, $keepCount);
    }
}
