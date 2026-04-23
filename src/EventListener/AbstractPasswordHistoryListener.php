<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener;

use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\PasswordExpirationAdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\PasswordExpirationShopUserInterface;

/**
 * @template T of object
 */
abstract class AbstractPasswordHistoryListener implements PasswordHistoryListenerInterface
{
    /** @var list<T> */
    private array $pendingEntries = [];

    private bool $isFlushing = false;

    private LoggerInterface $logger;

    public function __construct(
        private bool $enabled,
        private int $count,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if (!$this->supports($entity)) {
                continue;
            }

            $changeSet = $uow->getEntityChangeSet($entity);
            if (!array_key_exists('password', $changeSet)) {
                continue;
            }

            $this->stampPasswordChangedAt($entity);
            $uow->recomputeSingleEntityChangeSet($em->getClassMetadata($entity::class), $entity);

            $this->pendingEntries[] = $entity;
        }

        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            if (!$this->supports($entity)) {
                continue;
            }

            if ($this->getPassword($entity) === null) {
                continue;
            }

            $this->stampPasswordChangedAt($entity);
            $uow->recomputeSingleEntityChangeSet($em->getClassMetadata($entity::class), $entity);

            $this->pendingEntries[] = $entity;
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ($this->isFlushing || !$this->enabled || $this->pendingEntries === []) {
            return;
        }

        $this->isFlushing = true;
        $em = $args->getObjectManager();

        try {
            foreach ($this->pendingEntries as $user) {
                $entry = $this->createHistoryEntry($user);
                $em->persist($entry);
                $this->deleteOldEntries($user, $this->count - 1);
            }

            $this->pendingEntries = [];

            try {
                $em->flush();
            } catch (\Throwable $exception) {
                // Password history persistence must not break the main user flow.
                // If we fail here, the user's password has already been updated — we just lose the history row.
                $this->logger->error('Failed to persist password history entries.', ['exception' => $exception]);
            }
        } finally {
            $this->isFlushing = false;
        }
    }

    /**
     * @phpstan-assert-if-true T $entity
     */
    abstract protected function supports(object $entity): bool;

    /**
     * @param T $user
     */
    abstract protected function getPassword(object $user): ?string;

    /**
     * @param T $user
     */
    abstract protected function createHistoryEntry(object $user): object;

    /**
     * @param T $user
     */
    abstract protected function deleteOldEntries(object $user, int $keepCount): void;

    private function stampPasswordChangedAt(object $entity): void
    {
        if ($entity instanceof PasswordExpirationShopUserInterface || $entity instanceof PasswordExpirationAdminUserInterface) {
            $entity->setPasswordChangedAt(new \DateTimeImmutable());
            $entity->setForcePasswordChange(false);
        }
    }
}
