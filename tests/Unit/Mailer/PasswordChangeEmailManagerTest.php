<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Mailer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Mailer\Sender\SenderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer\Emails;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer\PasswordChangeEmailManager;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\UserAgentParserInterface;

#[CoversClass(PasswordChangeEmailManager::class)]
class PasswordChangeEmailManagerTest extends TestCase
{
    private function createManager(
        SenderInterface $sender,
        UrlGeneratorInterface $router,
        ?UserAgentParserInterface $userAgentParser = null,
    ): PasswordChangeEmailManager {
        if ($userAgentParser === null) {
            $userAgentParser = $this->createStub(UserAgentParserInterface::class);
            $userAgentParser->method('describe')->willReturn(null);
        }

        return new PasswordChangeEmailManager($sender, $router, $userAgentParser);
    }

    private function shopUserWithEmail(string $email): ShopUserInterface
    {
        $user = $this->createStub(ShopUserInterface::class);
        $user->method('getEmail')->willReturn($email);

        return $user;
    }

    private function shopUserWithNoEmail(): ShopUserInterface
    {
        $user = $this->createStub(ShopUserInterface::class);
        $user->method('getEmail')->willReturn(null);

        return $user;
    }

    private function adminUserWithEmail(string $email): AdminUserInterface
    {
        $user = $this->createStub(AdminUserInterface::class);
        $user->method('getEmail')->willReturn($email);

        return $user;
    }

    private function adminUserWithNoEmail(): AdminUserInterface
    {
        $user = $this->createStub(AdminUserInterface::class);
        $user->method('getEmail')->willReturn(null);

        return $user;
    }

    public function testSendShopUserEmail(): void
    {
        $sender = $this->createMock(SenderInterface::class);
        $sender->expects(self::once())
            ->method('send')
            ->with(Emails::PASSWORD_CHANGED, ['john@example.com'], self::callback(static fn ($value): bool => is_array($value)))
        ;

        $manager = $this->createManager($sender, $this->createStub(UrlGeneratorInterface::class));
        $manager->sendPasswordChangedEmail($this->shopUserWithEmail('john@example.com'), null, false);
    }

    public function testSendShopUserEmailDoesNothingWhenUserHasNoEmail(): void
    {
        $sender = $this->createMock(SenderInterface::class);
        $sender->expects(self::never())->method('send');

        $manager = $this->createManager($sender, $this->createStub(UrlGeneratorInterface::class));
        $manager->sendPasswordChangedEmail($this->shopUserWithNoEmail(), null, false);
    }

    public function testSendAdminUserEmail(): void
    {
        $sender = $this->createMock(SenderInterface::class);
        $sender->expects(self::once())
            ->method('send')
            ->with(Emails::PASSWORD_CHANGED, ['admin@example.com'], self::callback(static fn ($value): bool => is_array($value)))
        ;

        $manager = $this->createManager($sender, $this->createStub(UrlGeneratorInterface::class));
        $manager->sendPasswordChangedEmail($this->adminUserWithEmail('admin@example.com'), null, false);
    }

    public function testSendAdminUserEmailDoesNothingWhenUserHasNoEmail(): void
    {
        $sender = $this->createMock(SenderInterface::class);
        $sender->expects(self::never())->method('send');

        $manager = $this->createManager($sender, $this->createStub(UrlGeneratorInterface::class));
        $manager->sendPasswordChangedEmail($this->adminUserWithNoEmail(), null, false);
    }

    public function testEmailDataContainsIpFromRequest(): void
    {
        $request = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '1.2.3.4']);

        $capturedData = [];
        $sender = $this->createStub(SenderInterface::class);
        $sender->method('send')->willReturnCallback(
            function (string $code, array $recipients, array $data) use (&$capturedData): void {
                $capturedData = $data;
            },
        );

        $manager = $this->createManager($sender, $this->createStub(UrlGeneratorInterface::class));
        $manager->sendPasswordChangedEmail($this->shopUserWithEmail('john@example.com'), $request, false);

        self::assertSame('1.2.3.4', $capturedData['ipAddress']);
        self::assertInstanceOf(\DateTimeImmutable::class, $capturedData['timestamp']);
    }

    public function testEmailDataHasNullIpWithoutRequest(): void
    {
        $capturedData = [];
        $sender = $this->createStub(SenderInterface::class);
        $sender->method('send')->willReturnCallback(
            function (string $code, array $recipients, array $data) use (&$capturedData): void {
                $capturedData = $data;
            },
        );

        $manager = $this->createManager($sender, $this->createStub(UrlGeneratorInterface::class));
        $manager->sendPasswordChangedEmail($this->shopUserWithEmail('john@example.com'), null, false);

        self::assertNull($capturedData['ipAddress']);
        self::assertNull($capturedData['device']);
    }

    public function testEmailDataContainsParsedDeviceFromRequest(): void
    {
        $request = Request::create('/', 'GET', [], [], [], ['HTTP_USER_AGENT' => 'raw-ua']);

        $parser = $this->createMock(UserAgentParserInterface::class);
        $parser->expects(self::once())
            ->method('describe')
            ->with('raw-ua')
            ->willReturn('Chrome on macOS')
        ;

        $capturedData = [];
        $sender = $this->createStub(SenderInterface::class);
        $sender->method('send')->willReturnCallback(
            function (string $code, array $recipients, array $data) use (&$capturedData): void {
                $capturedData = $data;
            },
        );

        $manager = $this->createManager($sender, $this->createStub(UrlGeneratorInterface::class), $parser);
        $manager->sendPasswordChangedEmail($this->shopUserWithEmail('john@example.com'), $request, false);

        self::assertSame('Chrome on macOS', $capturedData['device']);
    }

    public function testSecureAccountUrlIsGeneratedWhenNotInitiatedByUser(): void
    {
        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->expects(self::once())
            ->method('generate')
            ->with('sylius_shop_request_password_reset_token', [], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://example.com/en_US/forgotten-password')
        ;

        $capturedData = [];
        $sender = $this->createStub(SenderInterface::class);
        $sender->method('send')->willReturnCallback(
            function (string $code, array $recipients, array $data) use (&$capturedData): void {
                $capturedData = $data;
            },
        );

        $manager = $this->createManager($sender, $router);
        $manager->sendPasswordChangedEmail($this->shopUserWithEmail('john@example.com'), null, false);

        self::assertSame('https://example.com/en_US/forgotten-password', $capturedData['secureAccountUrl']);
        self::assertFalse($capturedData['initiatedByUser']);
    }

    public function testSecureAccountUrlIsNullWhenInitiatedByUser(): void
    {
        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->expects(self::never())->method('generate');

        $capturedData = [];
        $sender = $this->createStub(SenderInterface::class);
        $sender->method('send')->willReturnCallback(
            function (string $code, array $recipients, array $data) use (&$capturedData): void {
                $capturedData = $data;
            },
        );

        $manager = $this->createManager($sender, $router);
        $manager->sendPasswordChangedEmail($this->shopUserWithEmail('john@example.com'), null, true);

        self::assertNull($capturedData['secureAccountUrl']);
        self::assertTrue($capturedData['initiatedByUser']);
    }

    public function testAdminEmailUsesAdminPasswordResetRouteForSecureUrl(): void
    {
        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->expects(self::once())
            ->method('generate')
            ->with('sylius_admin_request_password_reset', [], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://example.com/admin/forgotten-password')
        ;

        $capturedData = [];
        $sender = $this->createStub(SenderInterface::class);
        $sender->method('send')->willReturnCallback(
            function (string $code, array $recipients, array $data) use (&$capturedData): void {
                $capturedData = $data;
            },
        );

        $manager = $this->createManager($sender, $router);
        $manager->sendPasswordChangedEmail($this->adminUserWithEmail('admin@example.com'), null, false);

        self::assertSame('https://example.com/admin/forgotten-password', $capturedData['secureAccountUrl']);
    }

    public function testTimestampIsInUtc(): void
    {
        $capturedData = [];
        $sender = $this->createStub(SenderInterface::class);
        $sender->method('send')->willReturnCallback(
            function (string $code, array $recipients, array $data) use (&$capturedData): void {
                $capturedData = $data;
            },
        );

        $manager = $this->createManager($sender, $this->createStub(UrlGeneratorInterface::class));
        $manager->sendPasswordChangedEmail($this->shopUserWithEmail('john@example.com'), null, false);

        self::assertInstanceOf(\DateTimeImmutable::class, $capturedData['timestamp']);
        self::assertSame('UTC', $capturedData['timestamp']->getTimezone()->getName());
    }
}
