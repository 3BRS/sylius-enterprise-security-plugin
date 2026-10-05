<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use ThreeBRS\EnterpriseSecurityBundle\OAuth\AutoRegistrationPolicy;
use ThreeBRS\EnterpriseSecurityBundle\OAuth\OAuthUserInfo;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsProviderInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerSocialAccountLink;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerSocialAccountLinkInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerSocialAccountLinkRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\ShopSocialLoginHandler;

#[CoversClass(ShopSocialLoginHandler::class)]
class ShopSocialLoginHandlerTest extends TestCase
{
    public function testFindExistingLinkUserReturnsShopUserFromLink(): void
    {
        $shopUser = $this->createStub(ShopUserInterface::class);
        $link = $this->createStub(CustomerSocialAccountLinkInterface::class);
        $link->method('getShopUser')->willReturn($shopUser);

        $linkRepository = $this->createStub(CustomerSocialAccountLinkRepositoryInterface::class);
        $linkRepository->method('findByProviderAndProviderUserId')->willReturn($link);

        $handler = $this->handler(linkRepository: $linkRepository);

        self::assertSame($shopUser, $handler->findExistingLinkUser(new OAuthUserInfo('google', '123', 'a@b.com')));
    }

    public function testFindExistingLinkUserReturnsNullWhenLinkNotFound(): void
    {
        $linkRepository = $this->createStub(CustomerSocialAccountLinkRepositoryInterface::class);
        $linkRepository->method('findByProviderAndProviderUserId')->willReturn(null);

        $handler = $this->handler(linkRepository: $linkRepository);

        self::assertNull($handler->findExistingLinkUser(new OAuthUserInfo('google', '123', 'a@b.com')));
    }

    public function testFindUserByEmailReturnsShopUserFromCustomer(): void
    {
        $shopUser = $this->createStub(ShopUserInterface::class);
        $customer = $this->createStub(CustomerInterface::class);
        $customer->method('getUser')->willReturn($shopUser);

        $customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $customerRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['emailCanonical' => 'a@b.com'])
            ->willReturn($customer);

        $handler = $this->handler(customerRepository: $customerRepository);

        self::assertSame($shopUser, $handler->findUserByEmail('a@b.com'));
    }

    public function testFindUserByEmailReturnsNullWhenCustomerNotFound(): void
    {
        $customerRepository = $this->createStub(CustomerRepositoryInterface::class);
        $customerRepository->method('findOneBy')->willReturn(null);

        $handler = $this->handler(customerRepository: $customerRepository);

        self::assertNull($handler->findUserByEmail('a@b.com'));
    }

    public function testFindUserByEmailReturnsNullWhenCustomerHasNoShopUser(): void
    {
        $customer = $this->createStub(CustomerInterface::class);
        $customer->method('getUser')->willReturn(null);

        $customerRepository = $this->createStub(CustomerRepositoryInterface::class);
        $customerRepository->method('findOneBy')->willReturn($customer);

        $handler = $this->handler(customerRepository: $customerRepository);

        self::assertNull($handler->findUserByEmail('a@b.com'));
    }

    public function testRegisterAndLinkCreatesCustomerUserAndLink(): void
    {
        $customer = $this->createMock(CustomerInterface::class);
        $customer->expects($this->once())->method('setEmail')->with('new@example.com');
        $customer->expects($this->once())->method('setFirstName')->with('John');
        $customer->expects($this->once())->method('setLastName')->with('Doe');

        $shopUser = $this->createMock(ShopUserInterface::class);
        $shopUser->expects($this->once())->method('setCustomer')->with($customer);
        $shopUser->expects($this->once())->method('setEnabled')->with(true);
        $shopUser->expects($this->once())->method('setVerifiedAt')->with(self::isInstanceOf(\DateTimeImmutable::class));
        $shopUser->expects($this->never())->method('setPlainPassword');

        $customerFactory = $this->createStub(FactoryInterface::class);
        $customerFactory->method('createNew')->willReturn($customer);

        $shopUserFactory = $this->createStub(FactoryInterface::class);
        $shopUserFactory->method('createNew')->willReturn($shopUser);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $persisted = [];
        $entityManager->expects($this->exactly(3))
            ->method('persist')
            ->willReturnCallback(static function (object $entity) use (&$persisted): void {
                $persisted[] = $entity;
            });
        $entityManager->expects($this->once())->method('flush');

        $handler = $this->handler(
            customerFactory: $customerFactory,
            shopUserFactory: $shopUserFactory,
            entityManager: $entityManager,
        );

        $result = $handler->registerAndLink(new OAuthUserInfo('google', 'g-123', 'new@example.com', 'John', 'Doe'));

        self::assertSame($shopUser, $result);
        self::assertSame($customer, $persisted[0]);
        self::assertSame($shopUser, $persisted[1]);
        self::assertInstanceOf(CustomerSocialAccountLink::class, $persisted[2]);
        self::assertSame('google', $persisted[2]->getProvider());
        self::assertSame('g-123', $persisted[2]->getProviderUserId());
        self::assertSame('new@example.com', $persisted[2]->getEmail());
        self::assertSame($shopUser, $persisted[2]->getShopUser());
    }

    public function testRegisterAndLinkCreatesTheAccountOnTheGuestCustomer(): void
    {
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getUser')->willReturn(null);
        $customer->method('getFirstName')->willReturn('Jane');
        $customer->method('getLastName')->willReturn(null);
        $customer->expects($this->never())->method('setEmail');
        $customer->expects($this->never())->method('setFirstName');
        $customer->expects($this->once())->method('setLastName')->with('Doe');

        $customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $customerRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['emailCanonical' => 'guest@example.com'])
            ->willReturn($customer);

        $customerFactory = $this->createMock(FactoryInterface::class);
        $customerFactory->expects($this->never())->method('createNew');

        $shopUser = $this->createMock(ShopUserInterface::class);
        $shopUser->expects($this->once())->method('setCustomer')->with($customer);
        $shopUser->expects($this->once())->method('setEnabled')->with(true);

        $shopUserFactory = $this->createStub(FactoryInterface::class);
        $shopUserFactory->method('createNew')->willReturn($shopUser);

        $handler = $this->handler(
            customerRepository: $customerRepository,
            customerFactory: $customerFactory,
            shopUserFactory: $shopUserFactory,
        );

        $result = $handler->registerAndLink(new OAuthUserInfo('google', 'g-guest', 'Guest@Example.com', 'John', 'Doe', true));

        self::assertSame($shopUser, $result);
    }

    public function testRegisterAndLinkRefusesAGuestCustomerWhoseEmailTheProviderDidNotVerify(): void
    {
        $customer = $this->createStub(CustomerInterface::class);
        $customer->method('getUser')->willReturn(null);

        $customerRepository = $this->createStub(CustomerRepositoryInterface::class);
        $customerRepository->method('findOneBy')->willReturn($customer);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('flush');

        $handler = $this->handler(customerRepository: $customerRepository, entityManager: $entityManager);

        $this->expectException(\LogicException::class);
        $handler->registerAndLink(new OAuthUserInfo('microsoft', 'm-guest', 'guest@example.com'));
    }

    /**
     * @return iterable<string, array{CustomerInterface|null, bool|null, bool}>
     */
    public static function autoRegistrationProvider(): iterable
    {
        yield 'unknown email, verification not stated' => [null, null, true];
        yield 'guest customer, email verified' => [self::guestCustomer(), true, true];
        yield 'guest customer, verification not stated' => [self::guestCustomer(), null, false];
    }

    #[DataProvider('autoRegistrationProvider')]
    public function testAutoRegistrationTakesOverAGuestCustomerOnlyWithAVerifiedEmail(?CustomerInterface $customer, ?bool $emailVerified, bool $allowed): void
    {
        $customerRepository = $this->createStub(CustomerRepositoryInterface::class);
        $customerRepository->method('findOneBy')->willReturn($customer);

        $handler = $this->handler(customerRepository: $customerRepository);

        self::assertSame($allowed, $handler->canAutoRegister(new OAuthUserInfo('microsoft', 'm-1', 'guest@example.com', null, null, $emailVerified)));
    }

    public function testRegisterAndLinkRefusesACustomerThatAlreadyHasAnAccount(): void
    {
        $customer = $this->createStub(CustomerInterface::class);
        $customer->method('getUser')->willReturn($this->createStub(ShopUserInterface::class));

        $customerRepository = $this->createStub(CustomerRepositoryInterface::class);
        $customerRepository->method('findOneBy')->willReturn($customer);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('flush');

        $handler = $this->handler(customerRepository: $customerRepository, entityManager: $entityManager);

        $this->expectException(\LogicException::class);
        $handler->registerAndLink(new OAuthUserInfo('google', 'g-taken', 'taken@example.com'));
    }

    public function testRegisterAndLinkThrowsWhenEmailIsNull(): void
    {
        $handler = $this->handler();

        $this->expectException(\LogicException::class);
        $handler->registerAndLink(new OAuthUserInfo('google', 'g-123', null));
    }

    public function testRegisterAndLinkThrowsWhenEmailIsEmpty(): void
    {
        $handler = $this->handler();

        $this->expectException(\LogicException::class);
        $handler->registerAndLink(new OAuthUserInfo('google', 'g-123', ''));
    }

    public function testLinkExistingUserPersistsLinkAndFlushes(): void
    {
        $user = $this->createStub(ShopUserInterface::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);

        $persistedLink = null;
        $entityManager->expects($this->once())
            ->method('persist')
            ->willReturnCallback(static function (object $entity) use (&$persistedLink): void {
                $persistedLink = $entity;
            });
        $entityManager->expects($this->once())->method('flush');

        $handler = $this->handler(entityManager: $entityManager);

        $handler->linkExistingUser($user, new OAuthUserInfo('apple', 'a-42', 'x@y.com', 'X', 'Y'));

        self::assertInstanceOf(CustomerSocialAccountLink::class, $persistedLink);
        self::assertSame($user, $persistedLink->getShopUser());
        self::assertSame('apple', $persistedLink->getProvider());
        self::assertSame('a-42', $persistedLink->getProviderUserId());
        self::assertSame('x@y.com', $persistedLink->getEmail());
    }

    public function testTouchLastUsedUpdatesLinkWhenFound(): void
    {
        $user = $this->createStub(ShopUserInterface::class);

        $link = $this->createMock(CustomerSocialAccountLinkInterface::class);
        $link->expects($this->once())->method('setLastUsedAt')->with(self::isInstanceOf(\DateTimeImmutable::class));

        $linkRepository = $this->createStub(CustomerSocialAccountLinkRepositoryInterface::class);
        $linkRepository->method('findOneByShopUserAndProvider')->willReturn($link);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');

        $handler = $this->handler(linkRepository: $linkRepository, entityManager: $entityManager);

        $handler->touchLastUsed($user, new OAuthUserInfo('google', 'g-1', 'x@y.com'));
    }

    public function testTouchLastUsedDoesNothingWhenLinkMissing(): void
    {
        $user = $this->createStub(ShopUserInterface::class);

        $linkRepository = $this->createStub(CustomerSocialAccountLinkRepositoryInterface::class);
        $linkRepository->method('findOneByShopUserAndProvider')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('flush');

        $handler = $this->handler(linkRepository: $linkRepository, entityManager: $entityManager);

        $handler->touchLastUsed($user, new OAuthUserInfo('google', 'g-1', 'x@y.com'));
    }

    protected static function guestCustomer(): CustomerInterface
    {
        $customer = new Customer();
        $customer->setEmail('guest@example.com');

        return $customer;
    }

    protected function handler(
        ?CustomerRepositoryInterface $customerRepository = null,
        ?FactoryInterface $customerFactory = null,
        ?FactoryInterface $shopUserFactory = null,
        ?CustomerSocialAccountLinkRepositoryInterface $linkRepository = null,
        ?EntityManagerInterface $entityManager = null,
        ?SettingsProviderInterface $settings = null,
    ): ShopSocialLoginHandler {
        return new ShopSocialLoginHandler(
            $customerRepository ?? $this->createStub(CustomerRepositoryInterface::class),
            $customerFactory ?? $this->createStub(FactoryInterface::class),
            $shopUserFactory ?? $this->createStub(FactoryInterface::class),
            $linkRepository ?? $this->createStub(CustomerSocialAccountLinkRepositoryInterface::class),
            $entityManager ?? $this->createStub(EntityManagerInterface::class),
            $settings ?? $this->createStub(SettingsProviderInterface::class),
            new AutoRegistrationPolicy(),
        );
    }
}
