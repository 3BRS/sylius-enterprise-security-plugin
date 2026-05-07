<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Service\AccountDeletion;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerDeletionRequest;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerDeletionRequestInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer\AccountDeletionEmailManagerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerDeletionRequestRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\AccountDeletion\CustomerAnonymizerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\AccountDeletion\CustomerDeletionService;

#[CoversClass(CustomerDeletionService::class)]
class CustomerDeletionServiceTest extends TestCase
{
    public function testRequestDeletionDisablesShopUserAndPersistsRequest(): void
    {
        $shopUser = $this->createMock(ShopUserInterface::class);
        $shopUser->expects(self::once())->method('setEnabled')->with(false);

        $customer = $this->createStub(CustomerInterface::class);
        $customer->method('getUser')->willReturn($shopUser);

        $repository = $this->createMock(CustomerDeletionRequestRepositoryInterface::class);
        $repository->expects(self::once())->method('findActiveForCustomer')->willReturn(null);

        $email = $this->createMock(AccountDeletionEmailManagerInterface::class);
        $email->expects(self::once())->method('sendDeletionRequested');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist');
        $em->expects(self::once())->method('flush');

        $service = new CustomerDeletionService(
            $repository,
            $this->createStub(CustomerAnonymizerInterface::class),
            $email,
            $em,
            $this->fixedClock('2026-05-07 10:00:00'),
            30,
        );

        $request = $service->requestDeletion($customer);

        self::assertSame('2026-06-06 10:00:00', $request->getScheduledFor()->format('Y-m-d H:i:s'));
    }

    public function testRequestDeletionThrowsWhenAnotherRequestIsPending(): void
    {
        $existing = $this->createStub(CustomerDeletionRequestInterface::class);

        $repository = $this->createStub(CustomerDeletionRequestRepositoryInterface::class);
        $repository->method('findActiveForCustomer')->willReturn($existing);

        $service = new CustomerDeletionService(
            $repository,
            $this->createStub(CustomerAnonymizerInterface::class),
            $this->createStub(AccountDeletionEmailManagerInterface::class),
            $this->createStub(EntityManagerInterface::class),
            $this->fixedClock('2026-05-07 10:00:00'),
            30,
        );

        $this->expectException(\RuntimeException::class);
        $service->requestDeletion($this->createStub(CustomerInterface::class));
    }

    public function testCancelByAdminMarksRequestAndReEnablesShopUser(): void
    {
        $shopUser = $this->createMock(ShopUserInterface::class);
        $shopUser->expects(self::once())->method('setEnabled')->with(true);

        $customer = $this->createStub(CustomerInterface::class);
        $customer->method('getUser')->willReturn($shopUser);

        $request = new CustomerDeletionRequest();
        $request->setCustomer($customer);
        $request->setScheduledFor(new \DateTimeImmutable('2026-06-06 10:00:00'));

        $admin = $this->createStub(AdminUserInterface::class);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        $service = new CustomerDeletionService(
            $this->createStub(CustomerDeletionRequestRepositoryInterface::class),
            $this->createStub(CustomerAnonymizerInterface::class),
            $this->createStub(AccountDeletionEmailManagerInterface::class),
            $em,
            $this->fixedClock('2026-05-10 12:00:00'),
            30,
        );

        $service->cancelByAdmin($request, $admin);

        self::assertSame('2026-05-10 12:00:00', $request->getCancelledAt()?->format('Y-m-d H:i:s'));
        self::assertSame($admin, $request->getCancelledByAdmin());
        self::assertFalse($request->isPending());
    }

    public function testCancelByAdminThrowsWhenRequestIsNoLongerPending(): void
    {
        $request = new CustomerDeletionRequest();
        $request->setCustomer($this->createStub(CustomerInterface::class));
        $request->setScheduledFor(new \DateTimeImmutable('2026-06-06 10:00:00'));
        $request->setCompletedAt(new \DateTimeImmutable());

        $service = new CustomerDeletionService(
            $this->createStub(CustomerDeletionRequestRepositoryInterface::class),
            $this->createStub(CustomerAnonymizerInterface::class),
            $this->createStub(AccountDeletionEmailManagerInterface::class),
            $this->createStub(EntityManagerInterface::class),
            $this->fixedClock('2026-05-10 12:00:00'),
            30,
        );

        $this->expectException(\RuntimeException::class);
        $service->cancelByAdmin($request, $this->createStub(AdminUserInterface::class));
    }

    public function testProcessDueRequestsSendsEmailBeforeAnonymizing(): void
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

        $service = new CustomerDeletionService(
            $repository,
            $anonymizer,
            $email,
            $this->createStub(EntityManagerInterface::class),
            $this->fixedClock('2026-05-08 10:00:00'),
            30,
        );

        $count = $service->processDueRequests();

        self::assertSame(1, $count);
        self::assertSame(['email', 'anonymize'], $callOrder);
        self::assertSame('2026-05-08 10:00:00', $request->getCompletedAt()?->format('Y-m-d H:i:s'));
    }

    protected function fixedClock(string $iso): ClockInterface
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable($iso));

        return $clock;
    }
}
