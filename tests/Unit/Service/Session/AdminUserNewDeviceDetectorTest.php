<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Service\Session;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserKnownDeviceRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\AdminUserNewDeviceDetector;

#[CoversClass(AdminUserNewDeviceDetector::class)]
class AdminUserNewDeviceDetectorTest extends TestCase
{
    public function testReturnsTrueAndPersistsForUnknownFingerprint(): void
    {
        $repository = $this->createStub(AdminUserKnownDeviceRepositoryInterface::class);
        $repository->method('existsForAdminUser')->willReturn(false);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist');
        $em->expects(self::once())->method('flush');

        $detector = new AdminUserNewDeviceDetector($repository, $em);
        self::assertTrue($detector->checkAndRemember($this->createStub(AdminUserInterface::class), 'fp-1'));
    }

    public function testReturnsFalseForKnownFingerprint(): void
    {
        $repository = $this->createStub(AdminUserKnownDeviceRepositoryInterface::class);
        $repository->method('existsForAdminUser')->willReturn(true);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');

        $detector = new AdminUserNewDeviceDetector($repository, $em);
        self::assertFalse($detector->checkAndRemember($this->createStub(AdminUserInterface::class), 'fp-1'));
    }
}
