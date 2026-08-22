<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Security;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\UserBundle\Provider\UserProviderInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Security\AccountStateAwareUserProvider;

#[CoversClass(AccountStateAwareUserProvider::class)]
class AccountStateAwareUserProviderTest extends TestCase
{
    public function testAnEnabledUserIsRefreshedAsBefore(): void
    {
        $user = $this->shopUser(true);

        self::assertSame($user, $this->makeProvider($user)->refreshUser($user));
    }

    /**
     * The point of the decorator: a request from a session opened before the
     * account was disabled must not carry on. Symfony answers this exception by
     * dropping the token, which is what ends the session.
     */
    public function testADisabledUserIsRefusedSoTheSessionEnds(): void
    {
        $user = $this->shopUser(false);

        $this->expectException(UserNotFoundException::class);

        $this->makeProvider($user)->refreshUser($user);
    }

    /**
     * Sign-in runs its own checks — Sylius' user checker refuses a disabled
     * account there — so the load methods are handed on untouched.
     */
    public function testSignInLookupsAreHandedOnUntouched(): void
    {
        $user = $this->shopUser(false);
        $decorated = $this->createStub(UserProviderInterface::class);
        $decorated->method('loadUserByIdentifier')->willReturn($user);
        $decorated->method('loadUserByUsername')->willReturn($user);
        $decorated->method('supportsClass')->willReturn(true);

        $provider = new AccountStateAwareUserProvider($decorated);

        self::assertSame($user, $provider->loadUserByIdentifier('buyer@example.com'));
        self::assertSame($user, $provider->loadUserByUsername('buyer@example.com'));
        self::assertTrue($provider->supportsClass(ShopUserInterface::class));
    }

    /**
     * A user type outside Sylius' hierarchy has no enabled flag to read, and the
     * decorator has no business deciding for it.
     */
    public function testAUserWithoutSyliusAccountStateIsLeftAlone(): void
    {
        $user = $this->createStub(UserInterface::class);

        self::assertSame($user, $this->makeProvider($user)->refreshUser($user));
    }

    protected function makeProvider(UserInterface $refreshed): AccountStateAwareUserProvider
    {
        $decorated = $this->createStub(UserProviderInterface::class);
        $decorated->method('refreshUser')->willReturn($refreshed);

        return new AccountStateAwareUserProvider($decorated);
    }

    protected function shopUser(bool $enabled): ShopUserInterface
    {
        $user = $this->createStub(ShopUserInterface::class);
        $user->method('isEnabled')->willReturn($enabled);
        $user->method('getUserIdentifier')->willReturn('buyer@example.com');

        return $user;
    }
}
