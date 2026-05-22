<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Service\AccountDeletion;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Log\NullLogger;
use Sylius\Component\Core\Model\CustomerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerDeletionRequest;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer\AccountDeletionEmailManagerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerDeletionRequestRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\AccountDeletion\CustomerAnonymizerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\AccountDeletion\CustomerDueDeletionsProcessor;

#[CoversClass(CustomerDueDeletionsProcessor::class)]
class CustomerDueDeletionsProcessorTest extends TestCase
{
    public function testProcessSendsEmailBeforeAnonymizingAndStampsCompletedAt(): void
    {
        $customer = $this->createStub(CustomerInterface::class);

        $request = new CustomerDeletionRequest();
        $request->setCustomer($customer);
        $request->setScheduledFor(new \DateTimeImmutable('2026-05-01 10:00:00'));

        $repository = $this->createStub(CustomerDeletionRequestRepositoryInterface::class);
        $repository->method('findDue')->willReturn([$request]);

        $callOrder = [];

        $email = $this->createMock(AccountDeletionEmailManagerInterface::class);
        $email->expects(self::once())
            ->method('sendDeletionCompleted')
            ->with($customer)
            ->willReturnCallback(static function () use (&$callOrder): void {
                $callOrder[] = 'email';
            })
        ;

        $anonymizer = $this->createMock(CustomerAnonymizerInterface::class);
        $anonymizer->expects(self::once())
            ->method('anonymize')
            ->with($customer)
            ->willReturnCallback(static function () use (&$callOrder): void {
                $callOrder[] = 'anonymize';
            })
        ;

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        $now = new \DateTimeImmutable('2026-05-08 10:00:00');
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn($now);

        $processor = new CustomerDueDeletionsProcessor(
            $repository,
            $clock,
            $anonymizer,
            $email,
            $em,
            new NullLogger(),
        );

        $count = $processor->process();

        self::assertSame(1, $count);
        self::assertSame(['email', 'anonymize'], $callOrder);
        self::assertSame('2026-05-08 10:00:00', $request->getCompletedAt()?->format('Y-m-d H:i:s'));
    }
}
