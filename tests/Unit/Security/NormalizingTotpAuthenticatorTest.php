<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Security;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Security\NormalizingTotpAuthenticator;

#[CoversClass(NormalizingTotpAuthenticator::class)]
class NormalizingTotpAuthenticatorTest extends TestCase
{
    public function testTheCodeIsCheckedWithoutTheDashTheCodePageShows(): void
    {
        $user = $this->createStub(TwoFactorInterface::class);

        $inner = $this->createMock(TotpAuthenticatorInterface::class);
        $inner->expects(self::once())->method('checkCode')->with($user, '123456')->willReturn(true);

        self::assertTrue((new NormalizingTotpAuthenticator($inner))->checkCode($user, '123-456'));
    }

    public function testTheQrContentAndTheSecretComeFromScheb(): void
    {
        $user = $this->createStub(TwoFactorInterface::class);

        $inner = $this->createStub(TotpAuthenticatorInterface::class);
        $inner->method('getQRContent')->willReturn('otpauth://totp/Shop:john?secret=ABC');
        $inner->method('generateSecret')->willReturn('ABC');

        $authenticator = new NormalizingTotpAuthenticator($inner);

        self::assertSame('otpauth://totp/Shop:john?secret=ABC', $authenticator->getQRContent($user));
        self::assertSame('ABC', $authenticator->generateSecret());
    }
}
