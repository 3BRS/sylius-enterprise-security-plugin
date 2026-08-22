<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Model;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\LockableAdminUserTrait;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\LockableShopUserTrait;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\LockableUserTrait;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\PasswordExpirationAdminUserTrait;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\PasswordExpirationShopUserTrait;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\PasswordExpirationUserTrait;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\TwoFactorAuthAdminUserTrait;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\TwoFactorAuthShopUserTrait;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\TwoFactorAuthUserTrait;

/**
 * README tells integrators to use the shop- and admin-suffixed names, so both
 * stay. What must not come back is a second copy of the body behind them: the
 * two sides once held the same columns and the same behaviour twice over — the
 * TOTP period and digit count, the clamp in setFailedLoginAttempts — and a
 * change to either reached one firewall and left the other as it was.
 */
class UserTraitAliasTest extends TestCase
{
    /**
     * @return iterable<string, array{trait-string, trait-string}>
     */
    public static function aliasProvider(): iterable
    {
        yield 'lockable shop' => [LockableShopUserTrait::class, LockableUserTrait::class];
        yield 'lockable admin' => [LockableAdminUserTrait::class, LockableUserTrait::class];
        yield 'password expiration shop' => [PasswordExpirationShopUserTrait::class, PasswordExpirationUserTrait::class];
        yield 'password expiration admin' => [PasswordExpirationAdminUserTrait::class, PasswordExpirationUserTrait::class];
        yield 'two factor shop' => [TwoFactorAuthShopUserTrait::class, TwoFactorAuthUserTrait::class];
        yield 'two factor admin' => [TwoFactorAuthAdminUserTrait::class, TwoFactorAuthUserTrait::class];
    }

    /**
     * @param trait-string $alias
     * @param trait-string $shared
     */
    #[DataProvider('aliasProvider')]
    public function testTheSideSpecificNameOnlyForwardsToTheSharedTrait(string $alias, string $shared): void
    {
        $reflection = new \ReflectionClass($alias);

        self::assertSame([$shared], $reflection->getTraitNames());

        $sharedFile = (new \ReflectionClass($shared))->getFileName();
        foreach ($reflection->getMethods() as $method) {
            // PHP flattens a used trait into the using one, so getDeclaringClass()
            // names the alias either way; the file is what tells the bodies apart.
            self::assertSame(
                $sharedFile,
                $method->getFileName(),
                sprintf('%s() has a body of its own in %s.', $method->getName(), $alias),
            );
        }

        // Properties carry no file of their own in reflection, and they are the
        // half that maps columns, so read them off the source instead.
        $source = (string) file_get_contents((string) $reflection->getFileName());
        self::assertStringNotContainsString('ORM\\Column', $source, sprintf('%s maps columns of its own.', $alias));
        self::assertStringNotContainsString('function ', $source, sprintf('%s declares a method of its own.', $alias));
    }
}
