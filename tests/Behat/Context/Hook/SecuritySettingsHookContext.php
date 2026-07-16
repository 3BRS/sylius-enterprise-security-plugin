<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Hook;

use Behat\Behat\Context\Context;
use Doctrine\ORM\EntityManagerInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsProviderInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\SecuritySetting;

/**
 * Drops the DB-backed security settings before every Behat scenario, so every scenario starts from
 * the configured defaults.
 *
 * Sylius' ORM purger does not touch this table, so a scenario that turns something off (password
 * login, most notably) leaves the row behind for every scenario and every suite that follows —
 * including the many whose background signs in with a password. The settings provider caches what it
 * read, so it is refreshed along with the table.
 */
class SecuritySettingsHookContext implements Context
{
    public function __construct(
        protected EntityManagerInterface $entityManager,
        protected SettingsProviderInterface $settingsProvider,
    ) {
    }

    /** @BeforeScenario */
    public function resetSecuritySettings(): void
    {
        $this->entityManager->createQuery(sprintf('DELETE FROM %s', SecuritySetting::class))->execute();
        $this->settingsProvider->refresh();
    }
}
