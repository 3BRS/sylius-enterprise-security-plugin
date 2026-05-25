<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ThreeBRS\EnterpriseSecurityBundle\MagicLink\MagicLinkTokenGeneratorInterface;
use ThreeBRS\EnterpriseSecurityBundle\MagicLink\MagicLinkTokenValidatorInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerMagicLinkTokenInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerMagicLinkTokenRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\CustomerMagicLinkTokenVerifier;

#[CoversClass(CustomerMagicLinkTokenVerifier::class)]
class CustomerMagicLinkTokenVerifierTest extends TestCase
{
    public function testEmptyTokenReturnsNull(): void
    {
        $repo = $this->createMock(CustomerMagicLinkTokenRepositoryInterface::class);
        $repo->expects(self::never())->method('findOneByTokenHash');

        $verifier = new CustomerMagicLinkTokenVerifier(
            $repo,
            $this->createStub(MagicLinkTokenGeneratorInterface::class),
            $this->createStub(MagicLinkTokenValidatorInterface::class),
        );

        self::assertNull($verifier->verify(''));
    }

    public function testUnknownTokenReturnsNull(): void
    {
        $generator = $this->createMock(MagicLinkTokenGeneratorInterface::class);
        $generator->method('hash')->with('plain')->willReturn('hashed');

        $repo = $this->createMock(CustomerMagicLinkTokenRepositoryInterface::class);
        $repo->expects(self::once())->method('findOneByTokenHash')->with('hashed')->willReturn(null);

        $verifier = new CustomerMagicLinkTokenVerifier(
            $repo,
            $generator,
            $this->createStub(MagicLinkTokenValidatorInterface::class),
        );

        self::assertNull($verifier->verify('plain'));
    }

    public function testReturnsNullWhenValidatorRejectsToken(): void
    {
        $generator = $this->createStub(MagicLinkTokenGeneratorInterface::class);
        $generator->method('hash')->willReturn('hashed');

        $token = $this->createStub(CustomerMagicLinkTokenInterface::class);

        $repo = $this->createStub(CustomerMagicLinkTokenRepositoryInterface::class);
        $repo->method('findOneByTokenHash')->willReturn($token);

        $validator = $this->createMock(MagicLinkTokenValidatorInterface::class);
        $validator->expects(self::once())->method('isUsable')->with($token)->willReturn(false);

        $verifier = new CustomerMagicLinkTokenVerifier($repo, $generator, $validator);

        self::assertNull($verifier->verify('plain'));
    }

    public function testReturnsTokenWhenValidatorAcceptsIt(): void
    {
        $generator = $this->createStub(MagicLinkTokenGeneratorInterface::class);
        $generator->method('hash')->willReturn('hashed');

        $token = $this->createStub(CustomerMagicLinkTokenInterface::class);

        $repo = $this->createStub(CustomerMagicLinkTokenRepositoryInterface::class);
        $repo->method('findOneByTokenHash')->willReturn($token);

        $validator = $this->createStub(MagicLinkTokenValidatorInterface::class);
        $validator->method('isUsable')->willReturn(true);

        $verifier = new CustomerMagicLinkTokenVerifier($repo, $generator, $validator);

        self::assertSame($token, $verifier->verify('plain'));
    }
}
