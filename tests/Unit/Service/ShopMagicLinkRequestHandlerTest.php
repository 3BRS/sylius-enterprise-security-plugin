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
use ThreeBRS\EnterpriseSecurityBundle\MagicLink\MagicLinkTokenGeneratorInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\ShopMagicLinkRequestHandler;
use ThreeBRS\EnterpriseSecurityBundle\Timing\TimingPaddingInterface;

#[CoversClass(ShopMagicLinkRequestHandler::class)]
class ShopMagicLinkRequestHandlerTest extends TestCase
{
    public function testDisabledDoesNothing(): void
    {
        $customerRepo = $this->createMock(CustomerRepositoryInterface::class);
        $customerRepo->expects(self::never())->method('findOneBy');

        $generator = $this->createMock(MagicLinkTokenGeneratorInterface::class);
        $generator->expects(self::never())->method('generatePlainToken');

        $mailer = $this->createMock(CustomerMagicLinkEmailManagerInterface::class);
        $mailer->expects(self::never())->method('sendMagicLink');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');

        $clock = $this->createStub(ClockInterface::class);

        $timingPadding = $this->createMock(TimingPaddingInterface::class);
        $timingPadding->expects(self::never())->method('padTo');

        $handler = new ShopMagicLinkRequestHandler($customerRepo, $generator, $mailer, $em, $clock, $timingPadding, false, 300);

        $handler->request('john@example.com');
    }

    public function testEmptyEmailPadsResponseTime(): void
    {
        $customerRepo = $this->createMock(CustomerRepositoryInterface::class);
        $customerRepo->expects(self::never())->method('findOneBy');

        $generator = $this->createStub(MagicLinkTokenGeneratorInterface::class);
        $generator->method('generatePlainToken')->willReturn('plain-token');
        $generator->method('hash')->willReturn('hashed-token');

        $mailer = $this->createMock(CustomerMagicLinkEmailManagerInterface::class);
        $mailer->expects(self::never())->method('sendMagicLink');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-04-24 10:00:00'));

        $timingPadding = $this->createMock(TimingPaddingInterface::class);
        $timingPadding->expects(self::once())->method('padTo');

        $handler = new ShopMagicLinkRequestHandler($customerRepo, $generator, $mailer, $em, $clock, $timingPadding, true, 300);

        $handler->request('');
    }

    public function testUnknownEmailPadsResponseTime(): void
    {
        $customerRepo = $this->createMock(CustomerRepositoryInterface::class);
        $customerRepo->expects(self::once())->method('findOneBy')->willReturn(null);

        $generator = $this->createStub(MagicLinkTokenGeneratorInterface::class);
        $generator->method('generatePlainToken')->willReturn('plain-token');
        $generator->method('hash')->willReturn('hashed-token');

        $mailer = $this->createMock(CustomerMagicLinkEmailManagerInterface::class);
        $mailer->expects(self::never())->method('sendMagicLink');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-04-24 10:00:00'));

        $timingPadding = $this->createMock(TimingPaddingInterface::class);
        $timingPadding->expects(self::once())->method('padTo');

        $handler = new ShopMagicLinkRequestHandler($customerRepo, $generator, $mailer, $em, $clock, $timingPadding, true, 300);

        $handler->request('nobody@example.com');
    }

    public function testKnownEmailDispatchesMagicLinkAndPadsResponseTime(): void
    {
        $user = $this->createStub(ShopUserInterface::class);
        $customer = $this->createStub(CustomerInterface::class);
        $customer->method('getUser')->willReturn($user);

        $customerRepo = $this->createMock(CustomerRepositoryInterface::class);
        $customerRepo->expects(self::once())
            ->method('findOneBy')
            ->with(['emailCanonical' => 'john@example.com'])
            ->willReturn($customer);

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

        $timingPadding = $this->createMock(TimingPaddingInterface::class);
        $timingPadding->expects(self::once())->method('padTo');

        $handler = new ShopMagicLinkRequestHandler($customerRepo, $generator, $mailer, $em, $clock, $timingPadding, true, 300);

        $handler->request('john@example.com');
    }
}
