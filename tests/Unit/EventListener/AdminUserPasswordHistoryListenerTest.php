<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserPasswordHistory;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener\AbstractPasswordHistoryListener;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener\AdminUserPasswordHistoryListener;
use ThreeBRS\EnterpriseSecurityBundle\PasswordExpiration\PasswordExpirationAdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserPasswordHistoryRepositoryInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsProviderInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;

/** @internal test double: admin user with expiration tracking */
interface TestAdminUserForHistory extends AdminUserInterface, PasswordExpirationAdminUserInterface
{
}

#[CoversClass(AdminUserPasswordHistoryListener::class)]
#[CoversClass(AbstractPasswordHistoryListener::class)]
class AdminUserPasswordHistoryListenerTest extends TestCase
{
    private function createListener(
        ?AdminUserPasswordHistoryRepositoryInterface $repository = null,
        bool $enabled = true,
        int $count = 10,
    ): AdminUserPasswordHistoryListener {
        $settings = $this->createStub(SettingsProviderInterface::class);
        $settings->method('getBool')->willReturnCallback(static function (string $path, SettingsScope $scope) use ($enabled): bool {
            return $path === 'password_history.enabled' && $scope === SettingsScope::ADMIN ? $enabled : false;
        });
        $settings->method('getInt')->willReturnCallback(static function (string $path, SettingsScope $scope) use ($count): int {
            return $path === 'password_history.count' && $scope === SettingsScope::ADMIN ? $count : 0;
        });

        return new AdminUserPasswordHistoryListener(
            $repository ?? $this->createStub(AdminUserPasswordHistoryRepositoryInterface::class),
            $settings,
        );
    }

    /**
     * @param array<int, object>          $updates
     * @param array<int, object>          $insertions
     * @param array<string, array<mixed>> $changeSets keyed by spl_object_hash; each array-map of field => [old, new]
     */
    private function entityManagerStubForFlush(
        array $updates = [],
        array $insertions = [],
        array $changeSets = [],
    ): EntityManagerInterface {
        $uow = $this->createStub(UnitOfWork::class);
        $uow->method('getScheduledEntityUpdates')->willReturn($updates);
        $uow->method('getScheduledEntityInsertions')->willReturn($insertions);
        $uow->method('getEntityChangeSet')->willReturnCallback(
            static fn (object $entity) => $changeSets[spl_object_hash($entity)] ?? [],
        );

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getUnitOfWork')->willReturn($uow);
        $em->method('getClassMetadata')->willReturn($this->createStub(ClassMetadata::class));

        return $em;
    }

    private function adminUserStub(?string $password = '$2y$12$hash'): TestAdminUserForHistory
    {
        $user = $this->createStub(TestAdminUserForHistory::class);
        $user->method('getPassword')->willReturn($password);

        return $user;
    }

    public function testOnFlushIgnoresUpdateWithoutPasswordChange(): void
    {
        $user = $this->createMock(TestAdminUserForHistory::class);
        $user->expects(self::never())->method('setPasswordChangedAt');
        $user->expects(self::never())->method('setForcePasswordChange');

        $em = $this->entityManagerStubForFlush(
            updates: [$user],
            changeSets: [spl_object_hash($user) => ['email' => ['old', 'new']]],
        );

        $this->createListener()->onFlush(new OnFlushEventArgs($em));
    }

    public function testOnFlushSetsPasswordChangedAtAndClearsForceChangeOnUpdate(): void
    {
        $user = $this->createMock(TestAdminUserForHistory::class);
        $user->method('getPassword')->willReturn('$2y$12$somehash');
        $user->expects(self::once())->method('setPasswordChangedAt')->with(self::isInstanceOf(\DateTimeImmutable::class));
        $user->expects(self::once())->method('setForcePasswordChange')->with(false);

        $em = $this->entityManagerStubForFlush(
            updates: [$user],
            changeSets: [spl_object_hash($user) => ['password' => [null, '$2y$12$somehash']]],
        );

        $this->createListener()->onFlush(new OnFlushEventArgs($em));
    }

    public function testOnFlushSetsPasswordChangedAtOnInsertWithPassword(): void
    {
        $user = $this->createMock(TestAdminUserForHistory::class);
        $user->method('getPassword')->willReturn('$2y$12$somehash');
        $user->expects(self::once())->method('setPasswordChangedAt');
        $user->expects(self::once())->method('setForcePasswordChange')->with(false);

        $em = $this->entityManagerStubForFlush(insertions: [$user]);

        $this->createListener()->onFlush(new OnFlushEventArgs($em));
    }

    public function testOnFlushIgnoresInsertWithoutPassword(): void
    {
        $user = $this->createMock(TestAdminUserForHistory::class);
        $user->method('getPassword')->willReturn(null);
        $user->expects(self::never())->method('setPasswordChangedAt');

        $em = $this->entityManagerStubForFlush(insertions: [$user]);

        $this->createListener()->onFlush(new OnFlushEventArgs($em));
    }

    public function testPostFlushPersistsHistoryEntryAndTrimsOldEntries(): void
    {
        $user = $this->adminUserStub('$2y$12$hashForAdmin');

        $repository = $this->createMock(AdminUserPasswordHistoryRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('deleteOldEntriesForAdminUser')
            ->with($user, 9);

        $listener = $this->createListener(repository: $repository, count: 10);

        $onFlushEm = $this->entityManagerStubForFlush(
            updates: [$user],
            changeSets: [spl_object_hash($user) => ['password' => [null, '$2y$12$hashForAdmin']]],
        );
        $listener->onFlush(new OnFlushEventArgs($onFlushEm));

        $postFlushEm = $this->createMock(EntityManagerInterface::class);
        $postFlushEm->expects(self::once())
            ->method('persist')
            ->with(self::callback(
                static fn (object $entry): bool => $entry instanceof AdminUserPasswordHistory
                    && $entry->getAdminUser() === $user
                    && $entry->getPasswordHash() === '$2y$12$hashForAdmin',
            ));
        $postFlushEm->expects(self::once())->method('flush');

        $listener->postFlush(new PostFlushEventArgs($postFlushEm));
    }

    public function testPostFlushDoesNotPersistWhenDisabled(): void
    {
        $user = $this->adminUserStub();

        $repository = $this->createMock(AdminUserPasswordHistoryRepositoryInterface::class);
        $repository->expects(self::never())->method('deleteOldEntriesForAdminUser');

        $listener = $this->createListener(repository: $repository, enabled: false);

        $onFlushEm = $this->entityManagerStubForFlush(
            updates: [$user],
            changeSets: [spl_object_hash($user) => ['password' => [null, '$2y$12$hash']]],
        );
        $listener->onFlush(new OnFlushEventArgs($onFlushEm));

        $postFlushEm = $this->createMock(EntityManagerInterface::class);
        $postFlushEm->expects(self::never())->method('persist');

        $listener->postFlush(new PostFlushEventArgs($postFlushEm));
    }

    public function testPostFlushDoesNothingWhenNoPendingEntries(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');
        $em->expects(self::never())->method('flush');

        $this->createListener()->postFlush(new PostFlushEventArgs($em));
    }

    public function testOnFlushIgnoresNonAdminUserEntities(): void
    {
        $shop = $this->createStub(ShopUserInterface::class);
        $other = new \stdClass();

        $em = $this->entityManagerStubForFlush(
            updates: [$other, $shop],
            insertions: [$other, $shop],
        );

        $listener = $this->createListener();
        $listener->onFlush(new OnFlushEventArgs($em));

        $postEm = $this->createMock(EntityManagerInterface::class);
        $postEm->expects(self::never())->method('persist');
        $postEm->expects(self::never())->method('flush');

        $listener->postFlush(new PostFlushEventArgs($postEm));
    }
}
