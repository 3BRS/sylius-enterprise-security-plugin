<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener;

use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsProviderInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserPasswordHistory;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserPasswordHistoryRepositoryInterface;

/**
 * @extends AbstractPasswordHistoryListener<AdminUserInterface>
 */
class AdminUserPasswordHistoryListener extends AbstractPasswordHistoryListener implements AdminUserPasswordHistoryListenerInterface
{
    public function __construct(
        protected AdminUserPasswordHistoryRepositoryInterface $repository,
        SettingsProviderInterface $settings,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($settings, SettingsScope::ADMIN, $logger);
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
