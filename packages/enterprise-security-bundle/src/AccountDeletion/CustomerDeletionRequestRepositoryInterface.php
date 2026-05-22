<?php

declare(strict_types=1);

namespace ThreeBRS\EnterpriseSecurityBundle\AccountDeletion;

interface CustomerDeletionRequestRepositoryInterface
{
    /**
     * @return list<CustomerDeletionRequestRecordInterface>
     */
    public function findPendingForAdmin(): array;
}
