<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\FeatureToggleInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerLoginPreferenceRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\CustomerPasswordLoginCheckerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\LastAuthMethodGuardInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Twig\PasswordLoginControlExtension;

#[CoversClass(PasswordLoginControlExtension::class)]
class PasswordLoginControlExtensionTest extends TestCase
{
    public function testCustomerStatusAggregatesFeaturePreferenceAndGuard(): void
    {
        $featureToggle = $this->createStub(FeatureToggleInterface::class);
        $featureToggle->method('isEnabled')->willReturn(true);

        $repository = $this->createStub(CustomerLoginPreferenceRepositoryInterface::class);
        $repository->method('isPasswordLoginAllowedForUser')->willReturn(false);

        $guard = $this->createStub(LastAuthMethodGuardInterface::class);
        $guard->method('canDisablePasswordLoginForShopUser')->willReturn(true);

        $checker = $this->createStub(CustomerPasswordLoginCheckerInterface::class);

        $extension = new PasswordLoginControlExtension($featureToggle, $repository, $guard, $checker);

        self::assertSame(
            ['enabled' => true, 'allowed' => false, 'can_disable' => true],
            $extension->customerStatus($this->createStub(ShopUserInterface::class)),
        );
    }

    public function testExposesTheTwigFunctions(): void
    {
        $extension = new PasswordLoginControlExtension(
            $this->createStub(FeatureToggleInterface::class),
            $this->createStub(CustomerLoginPreferenceRepositoryInterface::class),
            $this->createStub(LastAuthMethodGuardInterface::class),
            $this->createStub(CustomerPasswordLoginCheckerInterface::class),
        );

        $names = array_map(
            static fn ($function): string => $function->getName(),
            $extension->getFunctions(),
        );

        self::assertContains('three_brs_customer_password_login_status', $names);
        self::assertContains('three_brs_customer_password_login_blocked', $names);
    }

    public function testCustomerPasswordLoginBlockedDelegatesToChecker(): void
    {
        $shopUser = $this->createStub(ShopUserInterface::class);

        $checker = $this->createMock(CustomerPasswordLoginCheckerInterface::class);
        $checker->expects(self::once())
            ->method('isPasswordLoginBlocked')
            ->with($shopUser)
            ->willReturn(true)
        ;

        $extension = new PasswordLoginControlExtension(
            $this->createStub(FeatureToggleInterface::class),
            $this->createStub(CustomerLoginPreferenceRepositoryInterface::class),
            $this->createStub(LastAuthMethodGuardInterface::class),
            $checker,
        );

        self::assertTrue($extension->customerPasswordLoginBlocked($shopUser));
    }
}
