<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity;

use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\AdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserKnownDeviceRepository;

#[ORM\Entity(repositoryClass: AdminUserKnownDeviceRepository::class)]
#[ORM\Table(name: 'three_brs_admin_user_known_device')]
#[ORM\UniqueConstraint(
    name: 'uniq_admin_user_known_device_user_fingerprint',
    columns: ['admin_user_id', 'fingerprint'],
)]
class AdminUserKnownDevice implements AdminUserKnownDeviceInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: 'integer')]
    protected ?int $id = null;

    #[ORM\ManyToOne(targetEntity: AdminUserInterface::class)]
    #[ORM\JoinColumn(name: 'admin_user_id', referencedColumnName: 'id', onDelete: 'CASCADE', nullable: false)]
    protected AdminUserInterface $adminUser;

    #[ORM\Column(name: 'fingerprint', type: 'string', length: 64, nullable: false)]
    protected string $fingerprint;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable', nullable: false)]
    protected \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAdminUser(): AdminUserInterface
    {
        return $this->adminUser;
    }

    public function setAdminUser(AdminUserInterface $adminUser): void
    {
        $this->adminUser = $adminUser;
    }

    public function getFingerprint(): string
    {
        return $this->fingerprint;
    }

    public function setFingerprint(string $fingerprint): void
    {
        $this->fingerprint = $fingerprint;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
