<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerMagicLinkTokenInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerMagicLinkTokenRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\CustomerMagicLinkTokenVerifier;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\MagicLinkTokenGeneratorInterface;

#[CoversClass(CustomerMagicLinkTokenVerifier::class)]
class CustomerMagicLinkTokenVerifierTest extends TestCase
{
    public function testEmptyTokenReturnsNull(): void
    {
        $repo = $this->createMock(CustomerMagicLinkTokenRepositoryInterface::class);
        $repo->expects(self::never())->method('findOneByTokenHash');

        $generator = $this->createStub(MagicLinkTokenGeneratorInterface::class);
        $clock = $this->createStub(ClockInterface::class);

        $verifier = new CustomerMagicLinkTokenVerifier($repo, $generator, $clock);

        self::assertNull($verifier->verify(''));
    }

    public function testUnknownTokenReturnsNull(): void
    {
        $generator = $this->createMock(MagicLinkTokenGeneratorInterface::class);
        $generator->method('hash')->with('plain')->willReturn('hashed');

        $repo = $this->createMock(CustomerMagicLinkTokenRepositoryInterface::class);
        $repo->expects(self::once())->method('findOneByTokenHash')->with('hashed')->willReturn(null);

        $clock = $this->createStub(ClockInterface::class);

        $verifier = new CustomerMagicLinkTokenVerifier($repo, $generator, $clock);

        self::assertNull($verifier->verify('plain'));
    }

    public function testAlreadyUsedTokenReturnsNull(): void
    {
        $generator = $this->createStub(MagicLinkTokenGeneratorInterface::class);
        $generator->method('hash')->willReturn('hashed');

        $token = $this->createStub(CustomerMagicLinkTokenInterface::class);
        $token->method('getUsedAt')->willReturn(new \DateTimeImmutable('2026-04-24 10:00:00'));

        $repo = $this->createStub(CustomerMagicLinkTokenRepositoryInterface::class);
        $repo->method('findOneByTokenHash')->willReturn($token);

        $clock = $this->createStub(ClockInterface::class);

        $verifier = new CustomerMagicLinkTokenVerifier($repo, $generator, $clock);

        self::assertNull($verifier->verify('plain'));
    }

    public function testExpiredTokenReturnsNull(): void
    {
        $generator = $this->createStub(MagicLinkTokenGeneratorInterface::class);
        $generator->method('hash')->willReturn('hashed');

        $token = $this->createStub(CustomerMagicLinkTokenInterface::class);
        $token->method('getUsedAt')->willReturn(null);
        $token->method('getExpiresAt')->willReturn(new \DateTimeImmutable('2026-04-24 09:00:00'));

        $repo = $this->createStub(CustomerMagicLinkTokenRepositoryInterface::class);
        $repo->method('findOneByTokenHash')->willReturn($token);

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-04-24 10:00:00'));

        $verifier = new CustomerMagicLinkTokenVerifier($repo, $generator, $clock);

        self::assertNull($verifier->verify('plain'));
    }

    public function testValidTokenIsReturned(): void
    {
        $generator = $this->createStub(MagicLinkTokenGeneratorInterface::class);
        $generator->method('hash')->willReturn('hashed');

        $token = $this->createStub(CustomerMagicLinkTokenInterface::class);
        $token->method('getUsedAt')->willReturn(null);
        $token->method('getExpiresAt')->willReturn(new \DateTimeImmutable('2026-04-24 11:00:00'));

        $repo = $this->createStub(CustomerMagicLinkTokenRepositoryInterface::class);
        $repo->method('findOneByTokenHash')->willReturn($token);

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-04-24 10:00:00'));

        $verifier = new CustomerMagicLinkTokenVerifier($repo, $generator, $clock);

        self::assertSame($token, $verifier->verify('plain'));
    }
}
