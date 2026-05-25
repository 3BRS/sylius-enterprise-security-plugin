<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity;

use Sylius\Component\Core\Model\AdminUserInterface;
use ThreeBRS\EnterpriseSecurityBundle\Session\SessionRecordInterface;

interface AdminUserSessionInterface extends SessionRecordInterface
{
    public function getId(): ?int;

    public function getAdminUser(): AdminUserInterface;

    public function setAdminUser(AdminUserInterface $adminUser): void;
}
