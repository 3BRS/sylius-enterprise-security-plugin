<?php

declare(strict_types=1);

namespace ThreeBRS\EnterpriseSecurityBundle\IpRestriction;

/**
 * Persisted per-user IP restriction record contract. Your entity (e.g.
 * `App\Entity\UserIpRestriction`) implements this plus its own user-relationship
 * accessors. The bundle's `AbstractIpRestrictionChecker` reads `enabled` +
 * `cidrs` from a record implementing this contract.
 */
interface IpRestrictionRecordInterface
{
    public function isEnabled(): bool;

    /**
     * @return list<string>
     */
    public function getCidrs(): array;
}
