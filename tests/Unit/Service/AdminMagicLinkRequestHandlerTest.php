<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer\AdminUserMagicLinkEmailManagerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserMagicLinkTokenRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\AdminMagicLinkRequestHandler;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\MagicLinkTokenGeneratorInterface;

#[CoversClass(AdminMagicLinkRequestHandler::class)]
class AdminMagicLinkRequestHandlerTest extends TestCase
{
    public function testDisabledDoesNothing(): void
    {
        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $userRepo->expects(self::never())->method('findOneBy');

        $tokenRepo = $this->createStub(AdminUserMagicLinkTokenRepositoryInterface::class);
        $generator = $this->createStub(MagicLinkTokenGeneratorInterface::class);
        $mailer = $this->createMock(AdminUserMagicLinkEmailManagerInterface::class);
        $mailer->expects(self::never())->method('sendMagicLink');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');

        $clock = $this->createStub(ClockInterface::class);

        $handler = new AdminMagicLinkRequestHandler($userRepo, $tokenRepo, $generator, $mailer, $em, $clock, false, 300, 3, 900);

        $handler->request('admin@example.com', '127.0.0.1');
    }

    public function testUnknownEmailDoesNothing(): void
    {
        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $userRepo->expects(self::once())->method('findOneBy')->willReturn(null);

        $tokenRepo = $this->createMock(AdminUserMagicLinkTokenRepositoryInterface::class);
        $tokenRepo->expects(self::never())->method('countRecentForAdminUser');

        $generator = $this->createStub(MagicLinkTokenGeneratorInterface::class);
        $mailer = $this->createMock(AdminUserMagicLinkEmailManagerInterface::class);
        $mailer->expects(self::never())->method('sendMagicLink');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');

        $clock = $this->createStub(ClockInterface::class);

        $handler = new AdminMagicLinkRequestHandler($userRepo, $tokenRepo, $generator, $mailer, $em, $clock, true, 300, 3, 900);

        $handler->request('nobody@example.com', '127.0.0.1');
    }

    public function testRateLimitBlocksSending(): void
    {
        $user = $this->createStub(AdminUserInterface::class);

        $userRepo = $this->createStub(UserRepositoryInterface::class);
        $userRepo->method('findOneBy')->willReturn($user);

        $tokenRepo = $this->createMock(AdminUserMagicLinkTokenRepositoryInterface::class);
        $tokenRepo->expects(self::once())->method('countRecentForAdminUser')->willReturn(3);

        $generator = $this->createMock(MagicLinkTokenGeneratorInterface::class);
        $generator->expects(self::never())->method('generatePlainToken');

        $mailer = $this->createMock(AdminUserMagicLinkEmailManagerInterface::class);
        $mailer->expects(self::never())->method('sendMagicLink');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-04-24 10:00:00'));

        $handler = new AdminMagicLinkRequestHandler($userRepo, $tokenRepo, $generator, $mailer, $em, $clock, true, 300, 3, 900);

        $handler->request('admin@example.com', '127.0.0.1');
    }

    public function testKnownEmailDispatchesMagicLink(): void
    {
        $user = $this->createStub(AdminUserInterface::class);

        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $userRepo->expects(self::once())
            ->method('findOneBy')
            ->with(['emailCanonical' => 'admin@example.com'])
            ->willReturn($user);

        $tokenRepo = $this->createStub(AdminUserMagicLinkTokenRepositoryInterface::class);
        $tokenRepo->method('countRecentForAdminUser')->willReturn(0);

        $generator = $this->createMock(MagicLinkTokenGeneratorInterface::class);
        $generator->expects(self::once())->method('generatePlainToken')->willReturn('plain-token');
        $generator->expects(self::once())->method('hash')->with('plain-token')->willReturn('hashed-token');

        $mailer = $this->createMock(AdminUserMagicLinkEmailManagerInterface::class);
        $mailer->expects(self::once())->method('sendMagicLink')->with($user, 'plain-token', 300);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist');
        $em->expects(self::once())->method('flush');

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-04-24 10:00:00'));

        $handler = new AdminMagicLinkRequestHandler($userRepo, $tokenRepo, $generator, $mailer, $em, $clock, true, 300, 3, 900);

        $handler->request('ADMIN@example.com', '127.0.0.1');
    }
}
