<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserRecoveryCodeInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerRecoveryCodeInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserRecoveryCodeRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerRecoveryCodeRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\RecoveryCodeGenerator;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\RecoveryCodeVerifier;

#[CoversClass(RecoveryCodeVerifier::class)]
class RecoveryCodeVerifierTest extends TestCase
{
    public function testShopUserCodeIsAcceptedAndMarkedUsed(): void
    {
        $now = new \DateTimeImmutable('2026-04-16 12:00:00');
        $user = $this->createStub(ShopUserInterface::class);
        $generator = new RecoveryCodeGenerator();
        $hash = $generator->hash('ABCDE-12345');

        $record = $this->createMock(CustomerRecoveryCodeInterface::class);
        $record->expects(self::once())->method('setUsedAt')->with($now);

        $customerRepo = $this->createMock(CustomerRecoveryCodeRepositoryInterface::class);
        $customerRepo->expects(self::once())
            ->method('findUnusedByShopUserAndHash')
            ->with($user, $hash)
            ->willReturn($record);

        $adminRepo = $this->createStub(AdminUserRecoveryCodeRepositoryInterface::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn($now);

        $verifier = new RecoveryCodeVerifier($customerRepo, $adminRepo, $generator, $em, $clock);

        self::assertTrue($verifier->verifyAndConsumeForShopUser($user, 'ABCDE-12345'));
    }

    public function testShopUserCodeIsRejectedWhenNotFound(): void
    {
        $user = $this->createStub(ShopUserInterface::class);
        $generator = new RecoveryCodeGenerator();

        $customerRepo = $this->createStub(CustomerRecoveryCodeRepositoryInterface::class);
        $customerRepo->method('findUnusedByShopUserAndHash')->willReturn(null);

        $adminRepo = $this->createStub(AdminUserRecoveryCodeRepositoryInterface::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');

        $clock = $this->createStub(ClockInterface::class);

        $verifier = new RecoveryCodeVerifier($customerRepo, $adminRepo, $generator, $em, $clock);

        self::assertFalse($verifier->verifyAndConsumeForShopUser($user, 'WRONG-CODE1'));
    }

    public function testAdminUserCodeIsAcceptedAndMarkedUsed(): void
    {
        $now = new \DateTimeImmutable('2026-04-16 12:00:00');
        $user = $this->createStub(AdminUserInterface::class);
        $generator = new RecoveryCodeGenerator();
        $hash = $generator->hash('ZZZZZ-99999');

        $record = $this->createMock(AdminUserRecoveryCodeInterface::class);
        $record->expects(self::once())->method('setUsedAt')->with($now);

        $customerRepo = $this->createStub(CustomerRecoveryCodeRepositoryInterface::class);
        $adminRepo = $this->createMock(AdminUserRecoveryCodeRepositoryInterface::class);
        $adminRepo->expects(self::once())
            ->method('findUnusedByAdminUserAndHash')
            ->with($user, $hash)
            ->willReturn($record);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn($now);

        $verifier = new RecoveryCodeVerifier($customerRepo, $adminRepo, $generator, $em, $clock);

        self::assertTrue($verifier->verifyAndConsumeForAdminUser($user, 'ZZZZZ-99999'));
    }

    public function testAdminUserCodeIsRejectedWhenNotFound(): void
    {
        $user = $this->createStub(AdminUserInterface::class);
        $generator = new RecoveryCodeGenerator();

        $customerRepo = $this->createStub(CustomerRecoveryCodeRepositoryInterface::class);
        $adminRepo = $this->createStub(AdminUserRecoveryCodeRepositoryInterface::class);
        $adminRepo->method('findUnusedByAdminUserAndHash')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');

        $clock = $this->createStub(ClockInterface::class);

        $verifier = new RecoveryCodeVerifier($customerRepo, $adminRepo, $generator, $em, $clock);

        self::assertFalse($verifier->verifyAndConsumeForAdminUser($user, 'WRONG-CODE1'));
    }
}
