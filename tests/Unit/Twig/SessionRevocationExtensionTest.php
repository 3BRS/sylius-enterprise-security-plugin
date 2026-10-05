<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserSession;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerSession;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserSessionRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerSessionRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Twig\SessionRevocationExtension;

#[CoversClass(SessionRevocationExtension::class)]
class SessionRevocationExtensionTest extends TestCase
{
    public function testAnotherUnrevokedCustomerSessionCountsEvenWhenItExpired(): void
    {
        $current = new CustomerSession();
        $current->setSessionId('sess-current');
        $expired = new CustomerSession();
        $expired->setSessionId('sess-expired');
        $expired->setLastActivityAt(new \DateTimeImmutable('-2 days'));

        $customerSessions = $this->createMock(CustomerSessionRepositoryInterface::class);
        $customerSessions->expects(self::never())->method('findActiveForShopUser');
        $customerSessions->method('findUnrevokedForShopUser')->willReturn([$current, $expired]);

        $extension = new SessionRevocationExtension($customerSessions, $this->createStub(AdminUserSessionRepositoryInterface::class));

        self::assertTrue($extension->hasOtherUnrevokedSessions($this->createStub(ShopUserInterface::class), 'sess-current'));
    }

    public function testTheCurrentSessionAloneDoesNotCount(): void
    {
        $current = new AdminUserSession();
        $current->setSessionId('sess-current');

        $adminSessions = $this->createStub(AdminUserSessionRepositoryInterface::class);
        $adminSessions->method('findUnrevokedForAdminUser')->willReturn([$current]);

        $extension = new SessionRevocationExtension($this->createStub(CustomerSessionRepositoryInterface::class), $adminSessions);

        self::assertFalse($extension->hasOtherUnrevokedSessions($this->createStub(AdminUserInterface::class), 'sess-current'));
    }

    public function testNobodySignedInHasNoSessions(): void
    {
        $extension = new SessionRevocationExtension(
            $this->createStub(CustomerSessionRepositoryInterface::class),
            $this->createStub(AdminUserSessionRepositoryInterface::class),
        );

        self::assertFalse($extension->hasOtherUnrevokedSessions(null, ''));
    }
}
