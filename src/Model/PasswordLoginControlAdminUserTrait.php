<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Model;

use Doctrine\ORM\Mapping as ORM;

trait PasswordLoginControlAdminUserTrait
{
    // Defaults to true so existing admins (and any created before the feature) keep
    // password login until an administrator deliberately turns it off.
    #[ORM\Column(name: 'password_login_allowed', type: 'boolean', options: ['default' => true])]
    protected bool $passwordLoginAllowed = true;

    public function isPasswordLoginAllowed(): bool
    {
        return $this->passwordLoginAllowed;
    }

    public function setPasswordLoginAllowed(bool $passwordLoginAllowed): void
    {
        $this->passwordLoginAllowed = $passwordLoginAllowed;
    }
}
