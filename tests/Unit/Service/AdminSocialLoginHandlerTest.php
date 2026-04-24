<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserSocialAccountLink;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserSocialAccountLinkInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\OAuth\OAuthUserInfo;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserSocialAccountLinkRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\AdminSocialLoginHandler;

#[CoversClass(AdminSocialLoginHandler::class)]
class AdminSocialLoginHandlerTest extends TestCase
{
    public function testFindExistingLinkUserReturnsAdminFromLink(): void
    {
        $admin = $this->createStub(AdminUserInterface::class);
        $link = $this->createStub(AdminUserSocialAccountLinkInterface::class);
        $link->method('getAdminUser')->willReturn($admin);

        $linkRepository = $this->createStub(AdminUserSocialAccountLinkRepositoryInterface::class);
        $linkRepository->method('findByProviderAndProviderUserId')->willReturn($link);

        $handler = new AdminSocialLoginHandler(
            $this->createStub(UserRepositoryInterface::class),
            $this->createStub(FactoryInterface::class),
            $linkRepository,
            $this->createStub(EntityManagerInterface::class),
            [],
            'en_US',
        );

        self::assertSame($admin, $handler->findExistingLinkUser(new OAuthUserInfo('google', '1', 'a@b.com')));
    }

    public function testFindUserByEmailLooksUpByCanonicalEmail(): void
    {
        $admin = $this->createStub(AdminUserInterface::class);

        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findOneBy')
            ->with(['emailCanonical' => 'a@b.com'])
            ->willReturn($admin);

        $handler = new AdminSocialLoginHandler(
            $repository,
            $this->createStub(FactoryInterface::class),
            $this->createStub(AdminUserSocialAccountLinkRepositoryInterface::class),
            $this->createStub(EntityManagerInterface::class),
            [],
            'en_US',
        );

        self::assertSame($admin, $handler->findUserByEmail('A@B.com'));
    }

    public function testRegisterAndLinkCreatesAdminAndGrantsAccessRole(): void
    {
        $admin = $this->createMock(AdminUserInterface::class);
        $admin->expects($this->once())->method('setEmail')->with('new@example.com');
        $admin->expects($this->once())->method('setUsername')->with('new@example.com');
        $admin->expects($this->once())->method('setFirstName')->with('John');
        $admin->expects($this->once())->method('setLastName')->with('Doe');
        $admin->expects($this->once())->method('setEnabled')->with(true);
        $admin->expects($this->never())->method('setPlainPassword');
        $admin->expects($this->once())->method('addRole')->with('ROLE_ADMINISTRATION_ACCESS');

        $adminFactory = $this->createStub(FactoryInterface::class);
        $adminFactory->method('createNew')->willReturn($admin);

        $persisted = [];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->exactly(2))
            ->method('persist')
            ->willReturnCallback(static function (object $entity) use (&$persisted): void {
                $persisted[] = $entity;
            });
        $entityManager->expects($this->once())->method('flush');

        $handler = new AdminSocialLoginHandler(
            $this->createStub(UserRepositoryInterface::class),
            $adminFactory,
            $this->createStub(AdminUserSocialAccountLinkRepositoryInterface::class),
            $entityManager,
            [],
            'en_US',
        );

        $result = $handler->registerAndLink(new OAuthUserInfo('google', 'g-1', 'new@example.com', 'John', 'Doe'));

        self::assertSame($admin, $result);
        self::assertSame($admin, $persisted[0]);
        self::assertInstanceOf(AdminUserSocialAccountLink::class, $persisted[1]);
        self::assertSame($admin, $persisted[1]->getAdminUser());
        self::assertSame('google', $persisted[1]->getProvider());
        self::assertSame('g-1', $persisted[1]->getProviderUserId());
    }

    public function testRegisterAndLinkUsesConfiguredDefaultLocale(): void
    {
        $admin = $this->createMock(AdminUserInterface::class);
        $admin->expects($this->once())->method('setLocaleCode')->with('cs_CZ');

        $adminFactory = $this->createStub(FactoryInterface::class);
        $adminFactory->method('createNew')->willReturn($admin);

        $handler = new AdminSocialLoginHandler(
            $this->createStub(UserRepositoryInterface::class),
            $adminFactory,
            $this->createStub(AdminUserSocialAccountLinkRepositoryInterface::class),
            $this->createStub(EntityManagerInterface::class),
            [],
            'cs_CZ',
        );

        $handler->registerAndLink(new OAuthUserInfo('google', 'g-1', 'new@example.com'));
    }

    public function testRegisterAndLinkThrowsWhenEmailMissing(): void
    {
        $handler = new AdminSocialLoginHandler(
            $this->createStub(UserRepositoryInterface::class),
            $this->createStub(FactoryInterface::class),
            $this->createStub(AdminUserSocialAccountLinkRepositoryInterface::class),
            $this->createStub(EntityManagerInterface::class),
            [],
            'en_US',
        );

        $this->expectException(\LogicException::class);
        $handler->registerAndLink(new OAuthUserInfo('google', 'g-1', null));
    }

    public function testLinkExistingUserPersistsLinkAndFlushes(): void
    {
        $admin = $this->createStub(AdminUserInterface::class);

        $persistedLink = null;
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())
            ->method('persist')
            ->willReturnCallback(static function (object $entity) use (&$persistedLink): void {
                $persistedLink = $entity;
            });
        $entityManager->expects($this->once())->method('flush');

        $handler = new AdminSocialLoginHandler(
            $this->createStub(UserRepositoryInterface::class),
            $this->createStub(FactoryInterface::class),
            $this->createStub(AdminUserSocialAccountLinkRepositoryInterface::class),
            $entityManager,
            [],
            'en_US',
        );

        $handler->linkExistingUser($admin, new OAuthUserInfo('apple', 'a-7', 'x@y.com'));

        self::assertInstanceOf(AdminUserSocialAccountLink::class, $persistedLink);
        self::assertSame('apple', $persistedLink->getProvider());
        self::assertSame('a-7', $persistedLink->getProviderUserId());
    }

    public function testTouchLastUsedDoesNothingWhenLinkMissing(): void
    {
        $admin = $this->createStub(AdminUserInterface::class);

        $linkRepository = $this->createStub(AdminUserSocialAccountLinkRepositoryInterface::class);
        $linkRepository->method('findOneByAdminUserAndProvider')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('flush');

        $handler = new AdminSocialLoginHandler(
            $this->createStub(UserRepositoryInterface::class),
            $this->createStub(FactoryInterface::class),
            $linkRepository,
            $entityManager,
            [],
            'en_US',
        );

        $handler->touchLastUsed($admin, new OAuthUserInfo('google', 'g-1', 'x@y.com'));
    }

    public function testTouchLastUsedUpdatesLinkWhenFound(): void
    {
        $admin = $this->createStub(AdminUserInterface::class);

        $link = $this->createMock(AdminUserSocialAccountLinkInterface::class);
        $link->expects($this->once())->method('setLastUsedAt')->with(self::isInstanceOf(\DateTimeImmutable::class));

        $linkRepository = $this->createStub(AdminUserSocialAccountLinkRepositoryInterface::class);
        $linkRepository->method('findOneByAdminUserAndProvider')->willReturn($link);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');

        $handler = new AdminSocialLoginHandler(
            $this->createStub(UserRepositoryInterface::class),
            $this->createStub(FactoryInterface::class),
            $linkRepository,
            $entityManager,
            [],
            'en_US',
        );

        $handler->touchLastUsed($admin, new OAuthUserInfo('google', 'g-1', 'x@y.com'));
    }
}
