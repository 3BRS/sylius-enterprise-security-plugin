<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserPasswordHistoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerPasswordHistoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserPasswordHistoryRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerPasswordHistoryRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\PasswordHistoryChecker;

#[CoversClass(PasswordHistoryChecker::class)]
class PasswordHistoryCheckerTest extends TestCase
{
    private function createChecker(
        array $customerEntries = [],
        array $adminEntries = [],
    ): PasswordHistoryChecker {
        $customerRepository = $this->createStub(CustomerPasswordHistoryRepositoryInterface::class);
        $customerRepository->method('findRecentByShopUser')->willReturn($customerEntries);

        $adminRepository = $this->createStub(AdminUserPasswordHistoryRepositoryInterface::class);
        $adminRepository->method('findRecentByAdminUser')->willReturn($adminEntries);

        return new PasswordHistoryChecker($customerRepository, $adminRepository);
    }

    public function testShopUserReturnsFalseWhenNoHistory(): void
    {
        $checker = $this->createChecker(customerEntries: []);

        self::assertFalse($checker->wasPasswordUsedByShopUser(
            $this->createStub(ShopUserInterface::class),
            'anypassword',
            5,
        ));
    }

    public function testShopUserReturnsTrueWhenPasswordMatchesStoredHash(): void
    {
        $plainPassword = 'mySecretPassword';

        $entry = $this->createStub(CustomerPasswordHistoryInterface::class);
        $entry->method('getPasswordHash')->willReturn(password_hash($plainPassword, \PASSWORD_BCRYPT));

        $checker = $this->createChecker(customerEntries: [$entry]);

        self::assertTrue($checker->wasPasswordUsedByShopUser(
            $this->createStub(ShopUserInterface::class),
            $plainPassword,
            5,
        ));
    }

    public function testShopUserReturnsFalseWhenPasswordDoesNotMatchAnyHash(): void
    {
        $entry = $this->createStub(CustomerPasswordHistoryInterface::class);
        $entry->method('getPasswordHash')->willReturn(password_hash('differentPassword', \PASSWORD_BCRYPT));

        $checker = $this->createChecker(customerEntries: [$entry]);

        self::assertFalse($checker->wasPasswordUsedByShopUser(
            $this->createStub(ShopUserInterface::class),
            'myNewPassword',
            5,
        ));
    }

    public function testShopUserChecksAllEntries(): void
    {
        $plainPassword = 'oldPassword';

        $entry1 = $this->createStub(CustomerPasswordHistoryInterface::class);
        $entry1->method('getPasswordHash')->willReturn(password_hash('differentPassword1', \PASSWORD_BCRYPT));

        $entry2 = $this->createStub(CustomerPasswordHistoryInterface::class);
        $entry2->method('getPasswordHash')->willReturn(password_hash($plainPassword, \PASSWORD_BCRYPT));

        $checker = $this->createChecker(customerEntries: [$entry1, $entry2]);

        self::assertTrue($checker->wasPasswordUsedByShopUser(
            $this->createStub(ShopUserInterface::class),
            $plainPassword,
            5,
        ));
    }

    public function testAdminUserReturnsFalseWhenNoHistory(): void
    {
        $checker = $this->createChecker(adminEntries: []);

        self::assertFalse($checker->wasPasswordUsedByAdminUser(
            $this->createStub(AdminUserInterface::class),
            'anypassword',
            10,
        ));
    }

    public function testAdminUserReturnsTrueWhenPasswordMatchesStoredHash(): void
    {
        $plainPassword = 'AdminSecret1!';

        $entry = $this->createStub(AdminUserPasswordHistoryInterface::class);
        $entry->method('getPasswordHash')->willReturn(password_hash($plainPassword, \PASSWORD_BCRYPT));

        $checker = $this->createChecker(adminEntries: [$entry]);

        self::assertTrue($checker->wasPasswordUsedByAdminUser(
            $this->createStub(AdminUserInterface::class),
            $plainPassword,
            10,
        ));
    }

    public function testAdminUserReturnsFalseWhenPasswordDoesNotMatchAnyHash(): void
    {
        $entry = $this->createStub(AdminUserPasswordHistoryInterface::class);
        $entry->method('getPasswordHash')->willReturn(password_hash('differentPassword', \PASSWORD_BCRYPT));

        $checker = $this->createChecker(adminEntries: [$entry]);

        self::assertFalse($checker->wasPasswordUsedByAdminUser(
            $this->createStub(AdminUserInterface::class),
            'NewAdminPass1!',
            10,
        ));
    }
}
