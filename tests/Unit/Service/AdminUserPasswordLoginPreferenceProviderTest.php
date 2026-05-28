<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\UserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\PasswordLoginControlAdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\AdminUserPasswordLoginPreferenceProvider;

/** @internal test double: a user that also carries the admin password-login flag */
interface TestPasswordLoginControlAdminUser extends UserInterface, PasswordLoginControlAdminUserInterface
{
}

#[CoversClass(AdminUserPasswordLoginPreferenceProvider::class)]
class AdminUserPasswordLoginPreferenceProviderTest extends TestCase
{
    public function testAllowsUsersThatDoNotCarryTheAdminFlag(): void
    {
        $provider = new AdminUserPasswordLoginPreferenceProvider();

        self::assertTrue($provider->isPasswordLoginAllowedForUser($this->createStub(UserInterface::class)));
    }

    public function testAllowsWhenAdminHasPasswordLoginEnabled(): void
    {
        $admin = $this->createStub(TestPasswordLoginControlAdminUser::class);
        $admin->method('isPasswordLoginAllowed')->willReturn(true);

        $provider = new AdminUserPasswordLoginPreferenceProvider();

        self::assertTrue($provider->isPasswordLoginAllowedForUser($admin));
    }

    public function testBlocksWhenAdminHasPasswordLoginDisabled(): void
    {
        $admin = $this->createStub(TestPasswordLoginControlAdminUser::class);
        $admin->method('isPasswordLoginAllowed')->willReturn(false);

        $provider = new AdminUserPasswordLoginPreferenceProvider();

        self::assertFalse($provider->isPasswordLoginAllowedForUser($admin));
    }
}
