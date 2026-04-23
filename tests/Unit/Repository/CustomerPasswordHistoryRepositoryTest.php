<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Repository;

use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerPasswordHistoryRepository;

#[CoversClass(CustomerPasswordHistoryRepository::class)]
class CustomerPasswordHistoryRepositoryTest extends TestCase
{
    public function testDeleteOldEntriesWithKeepCountZeroDeletesAllHistory(): void
    {
        $user = $this->createStub(ShopUserInterface::class);

        $query = $this->getMockBuilder(Query::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['execute'])
            ->getMock();
        $query->expects(self::once())->method('execute');

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('delete')->willReturnSelf();
        $queryBuilder->method('where')->with('h.shopUser = :user')->willReturnSelf();
        $queryBuilder->method('setParameter')->with('user', $user)->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);
        $queryBuilder->expects(self::never())->method('select');
        $queryBuilder->expects(self::never())->method('andWhere');

        $repository = $this->getMockBuilder(CustomerPasswordHistoryRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();
        $repository->method('createQueryBuilder')->with('h')->willReturn($queryBuilder);

        $repository->deleteOldEntriesForShopUser($user, 0);
    }
}
