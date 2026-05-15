<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Mailer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Mailer\Sender\SenderInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer\AccountDeletionEmailManager;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer\Emails;

#[CoversClass(AccountDeletionEmailManager::class)]
class AccountDeletionEmailManagerTest extends TestCase
{
    public function testSendsRequestedEmailWithScheduledForData(): void
    {
        $sender = $this->createMock(SenderInterface::class);
        $sender->expects(self::once())
            ->method('send')
            ->with(
                Emails::ACCOUNT_DELETION_REQUESTED,
                ['user@example.com'],
                self::callback(static fn (array $data): bool => $data['scheduledFor'] instanceof \DateTimeImmutable),
            )
        ;

        $customer = $this->createStub(CustomerInterface::class);
        $customer->method('getEmail')->willReturn('user@example.com');

        (new AccountDeletionEmailManager($sender))->sendDeletionRequested($customer, new \DateTimeImmutable('2026-06-06'));
    }

    public function testSendsCompletedEmailToCurrentEmail(): void
    {
        $sender = $this->createMock(SenderInterface::class);
        $sender->expects(self::once())
            ->method('send')
            ->with(
                Emails::ACCOUNT_DELETION_COMPLETED,
                ['user@example.com'],
                self::anything(),
            )
        ;

        $customer = $this->createStub(CustomerInterface::class);
        $customer->method('getEmail')->willReturn('user@example.com');

        (new AccountDeletionEmailManager($sender))->sendDeletionCompleted($customer);
    }

    public function testSkipsSendWhenCustomerHasNoEmail(): void
    {
        $sender = $this->createMock(SenderInterface::class);
        $sender->expects(self::never())->method('send');

        $customer = $this->createStub(CustomerInterface::class);
        $customer->method('getEmail')->willReturn(null);

        $manager = new AccountDeletionEmailManager($sender);
        $manager->sendDeletionRequested($customer, new \DateTimeImmutable('2026-06-06'));
        $manager->sendDeletionCompleted($customer);
    }
}
