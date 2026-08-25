<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Service\Session;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserKnownDeviceRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\AdminUserNewDeviceDetector;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserKnownDevice;

#[CoversClass(AdminUserNewDeviceDetector::class)]
class AdminUserNewDeviceDetectorTest extends TestCase
{
    public function testReturnsTrueAndPersistsForUnknownFingerprint(): void
    {
        $repository = $this->createStub(AdminUserKnownDeviceRepositoryInterface::class);
        $repository->method('existsForAdminUser')->willReturn(false);

        // Counting the write says a row was stored, not which. The fingerprint is the
        // whole of it — the row is only useful if the next sign-in can find it by the
        // same value.
        $stored = null;
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->willReturnCallback(
            function (object $entity) use (&$stored): void {
                $stored = $entity;
            },
        );
        $em->expects(self::once())->method('flush');

        $admin = $this->createStub(AdminUserInterface::class);
        $detector = new AdminUserNewDeviceDetector($repository, $em);
        self::assertTrue($detector->checkAndRemember($admin, 'fp-1'));

        self::assertInstanceOf(AdminUserKnownDevice::class, $stored);
        self::assertSame('fp-1', $stored->getFingerprint());
        self::assertSame($admin, $stored->getAdminUser());
    }

    public function testReturnsFalseAndSkipsPersistForKnownFingerprint(): void
    {
        $repository = $this->createStub(AdminUserKnownDeviceRepositoryInterface::class);
        $repository->method('existsForAdminUser')->willReturn(true);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');
        $em->expects(self::never())->method('flush');

        $detector = new AdminUserNewDeviceDetector($repository, $em);
        self::assertFalse($detector->checkAndRemember($this->createStub(AdminUserInterface::class), 'fp-1'));
    }
}
