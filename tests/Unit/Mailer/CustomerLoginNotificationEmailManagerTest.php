<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Mailer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Mailer\Sender\SenderInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer\CustomerLoginNotificationEmailManager;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer\Emails;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\UserAgentInfo;

#[CoversClass(CustomerLoginNotificationEmailManager::class)]
class CustomerLoginNotificationEmailManagerTest extends TestCase
{
    public function testSendsEmailWithExpectedDataPayload(): void
    {
        $sender = $this->createMock(SenderInterface::class);
        $sender->expects(self::once())
            ->method('send')
            ->with(
                Emails::LOGIN_NOTIFICATION,
                ['alice@example.com'],
                self::callback(static fn (array $data): bool =>
                    $data['group'] === 'customer'
                    && $data['ipAddress'] === '8.8.8.8'
                    && $data['country'] === 'US'
                    && $data['city'] === 'NYC'
                    && $data['browser'] === 'Chrome'
                    && $data['operatingSystem'] === 'Mac'
                    && $data['deviceType'] === 'desktop'
                ),
            )
        ;

        $user = $this->createStub(ShopUserInterface::class);
        $user->method('getEmail')->willReturn('alice@example.com');

        $manager = new CustomerLoginNotificationEmailManager($sender);
        $manager->sendNewDeviceNotification(
            $user,
            new \DateTimeImmutable('2026-04-30 10:00:00'),
            '8.8.8.8',
            'US',
            'NYC',
            new UserAgentInfo('Chrome', 'Mac', 'desktop'),
        );
    }

    public function testSkipsSendWhenUserHasNoEmail(): void
    {
        $sender = $this->createMock(SenderInterface::class);
        $sender->expects(self::never())->method('send');

        $user = $this->createStub(ShopUserInterface::class);
        $user->method('getEmail')->willReturn(null);

        $manager = new CustomerLoginNotificationEmailManager($sender);
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
