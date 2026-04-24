<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerSocialAccountLink;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerSocialAccountLinkInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\OAuth\OAuthUserInfo;
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

        $handler = new ShopSocialLoginHandler(
            $this->createStub(CustomerRepositoryInterface::class),
            $this->createStub(FactoryInterface::class),
            $this->createStub(FactoryInterface::class),
            $linkRepository,
            $this->createStub(EntityManagerInterface::class),
        );

        self::assertSame($shopUser, $handler->findExistingLinkUser(new OAuthUserInfo('google', '123', 'a@b.com')));
    }

    public function testFindExistingLinkUserReturnsNullWhenLinkNotFound(): void
    {
        $linkRepository = $this->createStub(CustomerSocialAccountLinkRepositoryInterface::class);
        $linkRepository->method('findByProviderAndProviderUserId')->willReturn(null);

        $handler = new ShopSocialLoginHandler(
            $this->createStub(CustomerRepositoryInterface::class),
            $this->createStub(FactoryInterface::class),
            $this->createStub(FactoryInterface::class),
            $linkRepository,
            $this->createStub(EntityManagerInterface::class),
        );

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
            ->with(['email' => 'a@b.com'])
            ->willReturn($customer);

        $handler = new ShopSocialLoginHandler(
            $customerRepository,
            $this->createStub(FactoryInterface::class),
            $this->createStub(FactoryInterface::class),
            $this->createStub(CustomerSocialAccountLinkRepositoryInterface::class),
            $this->createStub(EntityManagerInterface::class),
        );

        self::assertSame($shopUser, $handler->findUserByEmail('a@b.com'));
    }

    public function testFindUserByEmailReturnsNullWhenCustomerNotFound(): void
    {
        $customerRepository = $this->createStub(CustomerRepositoryInterface::class);
        $customerRepository->method('findOneBy')->willReturn(null);

        $handler = new ShopSocialLoginHandler(
            $customerRepository,
            $this->createStub(FactoryInterface::class),
            $this->createStub(FactoryInterface::class),
            $this->createStub(CustomerSocialAccountLinkRepositoryInterface::class),
            $this->createStub(EntityManagerInterface::class),
        );

        self::assertNull($handler->findUserByEmail('a@b.com'));
    }

    public function testFindUserByEmailReturnsNullWhenCustomerHasNoShopUser(): void
    {
        $customer = $this->createStub(CustomerInterface::class);
        $customer->method('getUser')->willReturn(null);

        $customerRepository = $this->createStub(CustomerRepositoryInterface::class);
        $customerRepository->method('findOneBy')->willReturn($customer);

        $handler = new ShopSocialLoginHandler(
            $customerRepository,
            $this->createStub(FactoryInterface::class),
            $this->createStub(FactoryInterface::class),
            $this->createStub(CustomerSocialAccountLinkRepositoryInterface::class),
            $this->createStub(EntityManagerInterface::class),
        );

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

        $handler = new ShopSocialLoginHandler(
            $this->createStub(CustomerRepositoryInterface::class),
            $customerFactory,
            $shopUserFactory,
            $this->createStub(CustomerSocialAccountLinkRepositoryInterface::class),
            $entityManager,
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

    public function testRegisterAndLinkThrowsWhenEmailIsNull(): void
    {
        $handler = new ShopSocialLoginHandler(
            $this->createStub(CustomerRepositoryInterface::class),
            $this->createStub(FactoryInterface::class),
            $this->createStub(FactoryInterface::class),
            $this->createStub(CustomerSocialAccountLinkRepositoryInterface::class),
            $this->createStub(EntityManagerInterface::class),
        );

        $this->expectException(\LogicException::class);
        $handler->registerAndLink(new OAuthUserInfo('google', 'g-123', null));
    }

    public function testRegisterAndLinkThrowsWhenEmailIsEmpty(): void
    {
        $handler = new ShopSocialLoginHandler(
            $this->createStub(CustomerRepositoryInterface::class),
            $this->createStub(FactoryInterface::class),
            $this->createStub(FactoryInterface::class),
            $this->createStub(CustomerSocialAccountLinkRepositoryInterface::class),
            $this->createStub(EntityManagerInterface::class),
        );

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

        $handler = new ShopSocialLoginHandler(
            $this->createStub(CustomerRepositoryInterface::class),
            $this->createStub(FactoryInterface::class),
            $this->createStub(FactoryInterface::class),
            $this->createStub(CustomerSocialAccountLinkRepositoryInterface::class),
            $entityManager,
        );

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

        $handler = new ShopSocialLoginHandler(
            $this->createStub(CustomerRepositoryInterface::class),
            $this->createStub(FactoryInterface::class),
            $this->createStub(FactoryInterface::class),
            $linkRepository,
            $entityManager,
        );

        $handler->touchLastUsed($user, new OAuthUserInfo('google', 'g-1', 'x@y.com'));
    }

    public function testTouchLastUsedDoesNothingWhenLinkMissing(): void
    {
        $user = $this->createStub(ShopUserInterface::class);

        $linkRepository = $this->createStub(CustomerSocialAccountLinkRepositoryInterface::class);
        $linkRepository->method('findOneByShopUserAndProvider')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('flush');

        $handler = new ShopSocialLoginHandler(
            $this->createStub(CustomerRepositoryInterface::class),
            $this->createStub(FactoryInterface::class),
            $this->createStub(FactoryInterface::class),
            $linkRepository,
            $entityManager,
        );

        $handler->touchLastUsed($user, new OAuthUserInfo('google', 'g-1', 'x@y.com'));
    }
}
