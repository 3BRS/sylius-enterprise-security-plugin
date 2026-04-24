<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer\CustomerMagicLinkEmailManagerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerMagicLinkTokenRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\MagicLinkTokenGeneratorInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\ShopMagicLinkRequestHandler;

#[CoversClass(ShopMagicLinkRequestHandler::class)]
class ShopMagicLinkRequestHandlerTest extends TestCase
{
    public function testDisabledDoesNothing(): void
    {
        $customerRepo = $this->createMock(CustomerRepositoryInterface::class);
        $customerRepo->expects(self::never())->method('findOneBy');

        $tokenRepo = $this->createStub(CustomerMagicLinkTokenRepositoryInterface::class);

        $generator = $this->createMock(MagicLinkTokenGeneratorInterface::class);
        $generator->expects(self::never())->method('generatePlainToken');

        $mailer = $this->createMock(CustomerMagicLinkEmailManagerInterface::class);
        $mailer->expects(self::never())->method('sendMagicLink');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');

        $clock = $this->createStub(ClockInterface::class);

        $handler = new ShopMagicLinkRequestHandler($customerRepo, $tokenRepo, $generator, $mailer, $em, $clock, false, 300, 3, 900);

        $handler->request('john@example.com');
    }

    public function testEmptyEmailDoesNothing(): void
    {
        $customerRepo = $this->createMock(CustomerRepositoryInterface::class);
        $customerRepo->expects(self::never())->method('findOneBy');

        $tokenRepo = $this->createStub(CustomerMagicLinkTokenRepositoryInterface::class);

        $generator = $this->createStub(MagicLinkTokenGeneratorInterface::class);
        $generator->method('generatePlainToken')->willReturn('plain-token');
        $generator->method('hash')->willReturn('hashed-token');

        $mailer = $this->createMock(CustomerMagicLinkEmailManagerInterface::class);
        $mailer->expects(self::never())->method('sendMagicLink');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-04-24 10:00:00'));

        $handler = new ShopMagicLinkRequestHandler($customerRepo, $tokenRepo, $generator, $mailer, $em, $clock, true, 300, 3, 900);

        $handler->request('');
    }

    public function testUnknownEmailDoesNothing(): void
    {
        $customerRepo = $this->createMock(CustomerRepositoryInterface::class);
        $customerRepo->expects(self::once())->method('findOneBy')->willReturn(null);

        $tokenRepo = $this->createMock(CustomerMagicLinkTokenRepositoryInterface::class);
        $tokenRepo->expects(self::never())->method('countRecentForShopUser');

        $generator = $this->createStub(MagicLinkTokenGeneratorInterface::class);
        $generator->method('generatePlainToken')->willReturn('plain-token');
        $generator->method('hash')->willReturn('hashed-token');

        $mailer = $this->createMock(CustomerMagicLinkEmailManagerInterface::class);
        $mailer->expects(self::never())->method('sendMagicLink');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-04-24 10:00:00'));

        $handler = new ShopMagicLinkRequestHandler($customerRepo, $tokenRepo, $generator, $mailer, $em, $clock, true, 300, 3, 900);

        $handler->request('nobody@example.com');
    }

    public function testRateLimitBlocksSending(): void
    {
        $user = $this->createStub(ShopUserInterface::class);
        $customer = $this->createStub(CustomerInterface::class);
        $customer->method('getUser')->willReturn($user);

        $customerRepo = $this->createStub(CustomerRepositoryInterface::class);
        $customerRepo->method('findOneBy')->willReturn($customer);

        $tokenRepo = $this->createMock(CustomerMagicLinkTokenRepositoryInterface::class);
        $tokenRepo->expects(self::once())->method('countRecentForShopUser')->willReturn(3);

        $generator = $this->createStub(MagicLinkTokenGeneratorInterface::class);
        $generator->method('generatePlainToken')->willReturn('plain-token');
        $generator->method('hash')->willReturn('hashed-token');

        $mailer = $this->createMock(CustomerMagicLinkEmailManagerInterface::class);
        $mailer->expects(self::never())->method('sendMagicLink');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-04-24 10:00:00'));

        $handler = new ShopMagicLinkRequestHandler($customerRepo, $tokenRepo, $generator, $mailer, $em, $clock, true, 300, 3, 900);

        $handler->request('john@example.com');
    }

    public function testKnownEmailDispatchesMagicLink(): void
    {
        $user = $this->createStub(ShopUserInterface::class);
        $customer = $this->createStub(CustomerInterface::class);
        $customer->method('getUser')->willReturn($user);

        $customerRepo = $this->createMock(CustomerRepositoryInterface::class);
        $customerRepo->expects(self::once())
            ->method('findOneBy')
            ->with(['email' => 'john@example.com'])
            ->willReturn($customer);

        $tokenRepo = $this->createStub(CustomerMagicLinkTokenRepositoryInterface::class);
        $tokenRepo->method('countRecentForShopUser')->willReturn(0);

        $generator = $this->createMock(MagicLinkTokenGeneratorInterface::class);
        $generator->expects(self::once())->method('generatePlainToken')->willReturn('plain-token');
        $generator->expects(self::once())->method('hash')->with('plain-token')->willReturn('hashed-token');

        $mailer = $this->createMock(CustomerMagicLinkEmailManagerInterface::class);
        $mailer->expects(self::once())->method('sendMagicLink')->with($user, 'plain-token', 300);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist');
        $em->expects(self::once())->method('flush');

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-04-24 10:00:00'));

        $handler = new ShopMagicLinkRequestHandler($customerRepo, $tokenRepo, $generator, $mailer, $em, $clock, true, 300, 3, 900);

        $handler->request('john@example.com');
    }
}
