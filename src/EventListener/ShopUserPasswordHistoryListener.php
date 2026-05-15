<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener;

use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerPasswordHistory;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerPasswordHistoryRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings\SettingsProviderInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings\SettingsScope;

/**
 * @extends AbstractPasswordHistoryListener<ShopUserInterface>
 */
class ShopUserPasswordHistoryListener extends AbstractPasswordHistoryListener implements ShopUserPasswordHistoryListenerInterface
{
    public function __construct(
        protected CustomerPasswordHistoryRepositoryInterface $repository,
        SettingsProviderInterface $settings,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($settings, SettingsScope::CUSTOMER, $logger);
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
