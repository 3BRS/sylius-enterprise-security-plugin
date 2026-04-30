<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Mailer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Mailer\Sender\SenderInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer\AdminUserLoginNotificationEmailManager;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer\Emails;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\UserAgentInfo;

#[CoversClass(AdminUserLoginNotificationEmailManager::class)]
class AdminUserLoginNotificationEmailManagerTest extends TestCase
{
    public function testSendsEmailWithAdminGroup(): void
    {
        $sender = $this->createMock(SenderInterface::class);
        $sender->expects(self::once())
            ->method('send')
            ->with(
                Emails::LOGIN_NOTIFICATION,
                ['admin@example.com'],
                self::callback(static fn (array $data): bool => $data['group'] === 'admin'),
            )
        ;

        $user = $this->createStub(AdminUserInterface::class);
        $user->method('getEmail')->willReturn('admin@example.com');

        $manager = new AdminUserLoginNotificationEmailManager($sender);
        $manager->sendNewDeviceNotification(
            $user,
            new \DateTimeImmutable('2026-04-30 10:00:00'),
            null,
            null,
            null,
            new UserAgentInfo(null, null, null),
        );
    }

    public function testSkipsSendWhenUserHasNoEmail(): void
    {
        $sender = $this->createMock(SenderInterface::class);
        $sender->expects(self::never())->method('send');

        $user = $this->createStub(AdminUserInterface::class);
        $user->method('getEmail')->willReturn(null);

        $manager = new AdminUserLoginNotificationEmailManager($sender);
        $manager->sendNewDeviceNotification(
            $user,
            new \DateTimeImmutable('2026-04-30 10:00:00'),
            null,
            null,
            null,
            new UserAgentInfo(null, null, null),
        );
    }
}
