<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerSession;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerSessionInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerSessionRepositoryInterface;
use Webmozart\Assert\Assert;

/**
 * Puts a session row on a customer and reads it back.
 *
 * The combinations in §6 of docs/manual-test-plan.md keep asking what happens to
 * somebody's sessions while a different feature acts — account deletion, trusted
 * devices — and those live in suites that cannot load the session-management
 * context: it and Sylius' shop login context both define "I sign in with email …
 * and password …", and Behat refuses a suite that defines one step text twice.
 * Each context therefore words its own steps and shares the rows through here.
 */
trait SessionRecordFixtureTrait
{
    abstract protected function getEntityManager(): EntityManagerInterface;

    abstract protected function getSessionRepository(): CustomerSessionRepositoryInterface;

    protected function recordSessionFor(ShopUserInterface $user, string $sessionId): void
    {
        $record = new CustomerSession();
        $record->setShopUser($user);
        $record->setSessionId($sessionId);
        $record->setUserAgent('Mozilla/5.0 (Other)');
        $record->setIpAddress('203.0.113.10');

        $this->getEntityManager()->persist($record);
        $this->getEntityManager()->flush();
    }

    protected function findRecordedSession(string $sessionId): CustomerSessionInterface
    {
        // Cleared first: these assertions run after a controller or a command has
        // written through a different unit of work, and a stale identity map would
        // report the row as it was before.
        $this->getEntityManager()->clear();

        $record = $this->getSessionRepository()->findOneBySessionId($sessionId);
        Assert::notNull($record, sprintf('No session "%s" was recorded.', $sessionId));

        return $record;
    }
}
