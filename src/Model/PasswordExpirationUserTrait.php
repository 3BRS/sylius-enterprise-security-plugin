<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Model;

use Doctrine\ORM\Mapping as ORM;

trait PasswordExpirationUserTrait
{
    #[ORM\Column(name: 'password_changed_at', type: 'datetime_immutable', nullable: true)]
    protected ?\DateTimeImmutable $passwordChangedAt = null;

    #[ORM\Column(name: 'force_password_change', type: 'boolean', options: ['default' => false])]
    protected bool $forcePasswordChange = false;

    public function getPasswordChangedAt(): ?\DateTimeImmutable
    {
        return $this->passwordChangedAt;
    }

    public function setPasswordChangedAt(?\DateTimeImmutable $passwordChangedAt): void
    {
        $this->passwordChangedAt = $passwordChangedAt;
    }

    public function isForcePasswordChange(): bool
    {
        return $this->forcePasswordChange;
    }

    public function setForcePasswordChange(bool $forcePasswordChange): void
    {
        $this->forcePasswordChange = $forcePasswordChange;
    }
}
