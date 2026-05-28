<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Model;

interface PasswordLoginControlAdminUserInterface
{
    public function isPasswordLoginAllowed(): bool;

    public function setPasswordLoginAllowed(bool $passwordLoginAllowed): void;
}
