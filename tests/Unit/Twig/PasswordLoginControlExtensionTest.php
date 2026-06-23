<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\PasswordLoginCheckerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Twig\PasswordLoginControlExtension;
use Twig\TwigFunction;

#[CoversClass(PasswordLoginControlExtension::class)]
class PasswordLoginControlExtensionTest extends TestCase
{
    public function testExposesThePasswordLoginEnabledFunction(): void
    {
        $extension = new PasswordLoginControlExtension($this->createStub(PasswordLoginCheckerInterface::class));

        $names = array_map(
            static fn (TwigFunction $function): string => $function->getName(),
            $extension->getFunctions(),
        );

        self::assertContains('three_brs_password_login_enabled', $names);
    }

    public function testDelegatesToTheCheckerForTheGivenScope(): void
    {
        $checker = $this->createStub(PasswordLoginCheckerInterface::class);
        $checker->method('isEnabled')->willReturnMap([
            [SettingsScope::CUSTOMER, true],
            [SettingsScope::ADMIN, false],
        ]);

        $extension = new PasswordLoginControlExtension($checker);

        self::assertTrue($extension->isEnabled('customer'));
        self::assertFalse($extension->isEnabled('admin'));
    }
}
