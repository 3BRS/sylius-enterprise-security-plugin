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
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsProviderInterface;

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

        $handler = new ShopMagicLinkRequestHandler($customerRepo, $generator, $mailer, $em, $clock, $timingPadding, false, $this->makeSettings(300));

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

        $handler = new ShopMagicLinkRequestHandler($customerRepo, $generator, $mailer, $em, $clock, $timingPadding, true, $this->makeSettings(300));

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

        $handler = new ShopMagicLinkRequestHandler($customerRepo, $generator, $mailer, $em, $clock, $timingPadding, true, $this->makeSettings(300));

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

        $handler = new ShopMagicLinkRequestHandler($customerRepo, $generator, $mailer, $em, $clock, $timingPadding, true, $this->makeSettings(300));

        $handler->request('john@example.com');
    }

    /**
     * The lifetime field is offered in Security Settings, saved and read back into the
     * form, and for a while that was all it did — the link kept expiring on the value
     * the container was built with. Pinning the stamp rather than the call is what
     * tells the two apart.
     */
    public function testTheTokenExpiresOnTheLifetimeFromSettings(): void
    {
        $customer = $this->createStub(CustomerInterface::class);
        $customer->method('getUser')->willReturn($this->createStub(ShopUserInterface::class));

        $customerRepo = $this->createStub(CustomerRepositoryInterface::class);
        $customerRepo->method('findOneBy')->willReturn($customer);

        $stored = null;
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(function (object $entity) use (&$stored): void {
            $stored = $entity;
        });

        $handler = new ShopMagicLinkRequestHandler(
            $customerRepo,
            $this->createStub(MagicLinkTokenGeneratorInterface::class),
            $this->createStub(CustomerMagicLinkEmailManagerInterface::class),
            $em,
            $this->clockAt('2026-05-07 10:00:00'),
            $this->createStub(TimingPaddingInterface::class),
            true,
            $this->makeSettings(900),
        );

        $handler->request('john@example.com');

        self::assertNotNull($stored);
        self::assertSame('2026-05-07 10:15:00', $stored->getExpiresAt()->format('Y-m-d H:i:s'));
    }

    /**
     * Only something writing the row directly can put a value outside the range the
     * form accepts there, and a link that outlives its window is the wrong way to fail.
     */
    public function testAnOutOfRangeLifetimeIsClamped(): void
    {
        $customer = $this->createStub(CustomerInterface::class);
        $customer->method('getUser')->willReturn($this->createStub(ShopUserInterface::class));

        $customerRepo = $this->createStub(CustomerRepositoryInterface::class);
        $customerRepo->method('findOneBy')->willReturn($customer);

        $stored = null;
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(function (object $entity) use (&$stored): void {
            $stored = $entity;
        });

        $handler = new ShopMagicLinkRequestHandler(
            $customerRepo,
            $this->createStub(MagicLinkTokenGeneratorInterface::class),
            $this->createStub(CustomerMagicLinkEmailManagerInterface::class),
            $em,
            $this->clockAt('2026-05-07 10:00:00'),
            $this->createStub(TimingPaddingInterface::class),
            true,
            $this->makeSettings(999999),
        );

        $handler->request('john@example.com');

        self::assertNotNull($stored);
        // SecuritySettingsBounds::MAGIC_LINK_EXPIRATION_SECONDS_MAX — one hour.
        self::assertSame('2026-05-07 11:00:00', $stored->getExpiresAt()->format('Y-m-d H:i:s'));
    }

    /**
     * The value the administrator set, which is what the handler now reads.
     */
    protected function makeSettings(int $expirationSeconds = 300): SettingsProviderInterface
    {
        $settings = $this->createStub(SettingsProviderInterface::class);
        $settings->method('getInt')->willReturn($expirationSeconds);

        return $settings;
    }


    protected function clockAt(string $when): ClockInterface
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable($when));

        return $clock;
    }

}
