<?php

declare(strict_types=1);

namespace ThreeBRS\EnterpriseSecurityBundle\IpRestriction;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Repository contract for looking up per-user IP restriction records. The
 * bundle's `AbstractIpRestrictionChecker` uses this contract to fan out the
 * per-user CIDR check on top of the global setting list.
 */
interface IpRestrictionRepositoryInterface
{
    public function findOneByUser(UserInterface $user): ?IpRestrictionRecordInterface;

    /**
     * Used during pre-authentication / anonymous checks where the request's
     * user identity isn't known yet — the checker iterates all enabled
     * entries and matches against the request IP.
     *
     * @return list<IpRestrictionRecordInterface>
     */
    public function findAllEnabled(): array;
}
