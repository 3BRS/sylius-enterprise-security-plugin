<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener\PasswordChangeNotificationListener;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer\PasswordChangeEmailManagerInterface;

#[CoversClass(PasswordChangeNotificationListener::class)]
class PasswordChangeNotificationListenerTest extends TestCase
{
    private function createListener(
        PasswordChangeEmailManagerInterface $emailManager,
        ?RequestStack $requestStack = null,
        ?TokenStorageInterface $tokenStorage = null,
    ): PasswordChangeNotificationListener {
        return new PasswordChangeNotificationListener(
            $emailManager,
            $requestStack ?? $this->requestStackWithRequest(),
            $tokenStorage ?? $this->tokenStorageWithUser(null),
        );
    }

    private function requestStackWithRequest(?Request $request = null): RequestStack
    {
        $stack = $this->createStub(RequestStack::class);
        $stack->method('getCurrentRequest')->willReturn($request ?? new Request());

        return $stack;
    }

    private function tokenStorageWithUser(?object $user): TokenStorageInterface
    {
        $storage = $this->createStub(TokenStorageInterface::class);

        if ($user === null) {
            $storage->method('getToken')->willReturn(null);

            return $storage;
        }

        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);
        $storage->method('getToken')->willReturn($token);

        return $storage;
    }

    private function entityManagerWithUpdates(array $entities, array $changeSet): EntityManagerInterface
    {
        $uow = $this->createStub(UnitOfWork::class);
        $uow->method('getScheduledEntityUpdates')->willReturn($entities);
        $uow->method('getEntityChangeSet')->willReturn($changeSet);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getUnitOfWork')->willReturn($uow);

        return $em;
    }

    private function createPostFlushEvent(): PostFlushEventArgs
    {
        return new PostFlushEventArgs($this->createStub(EntityManagerInterface::class));
    }

    public function testSendsEmailForShopUserOnPasswordChange(): void
    {
        $shopUser = $this->createStub(ShopUserInterface::class);

        $emailManager = $this->createMock(PasswordChangeEmailManagerInterface::class);
        $emailManager->expects(self::once())->method('sendCustomerPasswordChangedEmail');

        $listener = $this->createListener($emailManager);
        $listener->onFlush(new OnFlushEventArgs($this->entityManagerWithUpdates([$shopUser], ['password' => ['old', 'new']])));
        $listener->postFlush($this->createPostFlushEvent());
    }

    public function testSendsEmailForAdminUserOnPasswordChange(): void
    {
        $adminUser = $this->createStub(AdminUserInterface::class);

        $emailManager = $this->createMock(PasswordChangeEmailManagerInterface::class);
        $emailManager->expects(self::once())->method('sendAdminPasswordChangedEmail');

        $listener = $this->createListener($emailManager);
        $listener->onFlush(new OnFlushEventArgs($this->entityManagerWithUpdates([$adminUser], ['password' => ['old', 'new']])));
        $listener->postFlush($this->createPostFlushEvent());
    }

    public function testIgnoresUsersWithoutPasswordChange(): void
    {
        $adminUser = $this->createStub(AdminUserInterface::class);

        $emailManager = $this->createMock(PasswordChangeEmailManagerInterface::class);
        $emailManager->expects(self::never())->method(self::anything());

        $listener = $this->createListener($emailManager);
        $listener->onFlush(new OnFlushEventArgs($this->entityManagerWithUpdates([$adminUser], ['username' => ['old', 'new']])));
        $listener->postFlush($this->createPostFlushEvent());
    }

    public function testIgnoresNonUserEntities(): void
    {
        $emailManager = $this->createMock(PasswordChangeEmailManagerInterface::class);
        $emailManager->expects(self::never())->method(self::anything());

        $listener = $this->createListener($emailManager);
        $listener->onFlush(new OnFlushEventArgs($this->entityManagerWithUpdates([new \stdClass()], ['password' => ['old', 'new']])));
        $listener->postFlush($this->createPostFlushEvent());
    }

    public function testMarksInitiatedByUserWhenCurrentUserMatchesTarget(): void
    {
        $shopUser = $this->createStub(ShopUserInterface::class);

        $emailManager = $this->createMock(PasswordChangeEmailManagerInterface::class);
        $emailManager->expects(self::once())
            ->method('sendCustomerPasswordChangedEmail')
            ->with($shopUser, self::anything(), true)
        ;

        $listener = $this->createListener($emailManager, tokenStorage: $this->tokenStorageWithUser($shopUser));
        $listener->onFlush(new OnFlushEventArgs($this->entityManagerWithUpdates([$shopUser], ['password' => ['old', 'new']])));
        $listener->postFlush($this->createPostFlushEvent());
    }

    public function testMarksNotInitiatedByUserWhenCurrentUserDiffersFromTarget(): void
    {
        $targetUser = $this->createStub(ShopUserInterface::class);
        $adminUser = $this->createStub(AdminUserInterface::class);

        $emailManager = $this->createMock(PasswordChangeEmailManagerInterface::class);
        $emailManager->expects(self::once())
            ->method('sendCustomerPasswordChangedEmail')
            ->with($targetUser, self::anything(), false)
        ;

        $listener = $this->createListener($emailManager, tokenStorage: $this->tokenStorageWithUser($adminUser));
        $listener->onFlush(new OnFlushEventArgs($this->entityManagerWithUpdates([$targetUser], ['password' => ['old', 'new']])));
        $listener->postFlush($this->createPostFlushEvent());
    }

    public function testMarksNotInitiatedByUserWhenNoAuthenticatedUser(): void
    {
        $shopUser = $this->createStub(ShopUserInterface::class);

        $emailManager = $this->createMock(PasswordChangeEmailManagerInterface::class);
        $emailManager->expects(self::once())
            ->method('sendCustomerPasswordChangedEmail')
            ->with($shopUser, self::anything(), false)
        ;

        $listener = $this->createListener($emailManager);
        $listener->onFlush(new OnFlushEventArgs($this->entityManagerWithUpdates([$shopUser], ['password' => ['old', 'new']])));
        $listener->postFlush($this->createPostFlushEvent());
    }

    public function testMarksInitiatedByUserDuringShopPasswordResetRoute(): void
    {
        $shopUser = $this->createStub(ShopUserInterface::class);

        $request = new Request();
        $request->attributes->set('_route', 'sylius_shop_password_reset');

        $emailManager = $this->createMock(PasswordChangeEmailManagerInterface::class);
        $emailManager->expects(self::once())
            ->method('sendCustomerPasswordChangedEmail')
            ->with($shopUser, self::anything(), true)
        ;

        $listener = $this->createListener($emailManager, requestStack: $this->requestStackWithRequest($request));
        $listener->onFlush(new OnFlushEventArgs($this->entityManagerWithUpdates([$shopUser], ['password' => ['old', 'new']])));
        $listener->postFlush($this->createPostFlushEvent());
    }

    public function testMarksInitiatedByUserDuringAdminPasswordResetRoute(): void
    {
        $adminUser = $this->createStub(AdminUserInterface::class);

        $request = new Request();
        $request->attributes->set('_route', 'sylius_admin_password_reset');

        $emailManager = $this->createMock(PasswordChangeEmailManagerInterface::class);
        $emailManager->expects(self::once())
            ->method('sendAdminPasswordChangedEmail')
            ->with($adminUser, self::anything(), true)
        ;

        $listener = $this->createListener($emailManager, requestStack: $this->requestStackWithRequest($request));
        $listener->onFlush(new OnFlushEventArgs($this->entityManagerWithUpdates([$adminUser], ['password' => ['old', 'new']])));
        $listener->postFlush($this->createPostFlushEvent());
    }

    public function testMarksInitiatedByUserDuringForcePasswordChangeRoute(): void
    {
        $adminUser = $this->createStub(AdminUserInterface::class);

        $request = new Request();
        $request->attributes->set('_route', 'three_brs_admin_force_password_change');

        $emailManager = $this->createMock(PasswordChangeEmailManagerInterface::class);
        $emailManager->expects(self::once())
            ->method('sendAdminPasswordChangedEmail')
            ->with($adminUser, self::anything(), true)
        ;

        $listener = $this->createListener($emailManager, requestStack: $this->requestStackWithRequest($request));
        $listener->onFlush(new OnFlushEventArgs($this->entityManagerWithUpdates([$adminUser], ['password' => ['old', 'new']])));
        $listener->postFlush($this->createPostFlushEvent());
    }

    public function testPostFlushDoesNothingWhenNoPendingNotifications(): void
    {
        $emailManager = $this->createMock(PasswordChangeEmailManagerInterface::class);
        $emailManager->expects(self::never())->method(self::anything());

        $listener = $this->createListener($emailManager);
        $listener->onFlush(new OnFlushEventArgs($this->entityManagerWithUpdates([], [])));
        $listener->postFlush($this->createPostFlushEvent());
    }

    public function testPostFlushClearsPendingNotificationsAfterSending(): void
    {
        $adminUser = $this->createStub(AdminUserInterface::class);

        $emailManager = $this->createMock(PasswordChangeEmailManagerInterface::class);
        $emailManager->expects(self::once())->method('sendAdminPasswordChangedEmail');

        $listener = $this->createListener($emailManager);
        $listener->onFlush(new OnFlushEventArgs($this->entityManagerWithUpdates([$adminUser], ['password' => ['old', 'new']])));
        $listener->postFlush($this->createPostFlushEvent());
        $listener->postFlush($this->createPostFlushEvent());
    }

    public function testDeduplicatesSameUserFlushedTwice(): void
    {
        $adminUser = $this->createStub(AdminUserInterface::class);

        $emailManager = $this->createMock(PasswordChangeEmailManagerInterface::class);
        $emailManager->expects(self::once())->method('sendAdminPasswordChangedEmail');

        $listener = $this->createListener($emailManager);
        $listener->onFlush(new OnFlushEventArgs($this->entityManagerWithUpdates([$adminUser, $adminUser], ['password' => ['old', 'new']])));
        $listener->postFlush($this->createPostFlushEvent());
    }
}
