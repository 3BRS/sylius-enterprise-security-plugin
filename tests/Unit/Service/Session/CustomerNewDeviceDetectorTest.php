<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Service\Session;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerKnownDeviceRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\CustomerNewDeviceDetector;

#[CoversClass(CustomerNewDeviceDetector::class)]
class CustomerNewDeviceDetectorTest extends TestCase
{
    public function testReturnsTrueAndPersistsForUnknownFingerprint(): void
    {
        $repository = $this->createStub(CustomerKnownDeviceRepositoryInterface::class);
        $repository->method('existsForShopUser')->willReturn(false);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist');
        $em->expects(self::once())->method('flush');

        $detector = new CustomerNewDeviceDetector($repository, $em);
        self::assertTrue($detector->checkAndRemember($this->createStub(ShopUserInterface::class), 'fp-1'));
    }

    public function testReturnsFalseAndSkipsPersistForKnownFingerprint(): void
    {
        $repository = $this->createStub(CustomerKnownDeviceRepositoryInterface::class);
        $repository->method('existsForShopUser')->willReturn(true);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');
        $em->expects(self::never())->method('flush');

        $detector = new CustomerNewDeviceDetector($repository, $em);
        self::assertFalse($detector->checkAndRemember($this->createStub(ShopUserInterface::class), 'fp-1'));
    }
}
