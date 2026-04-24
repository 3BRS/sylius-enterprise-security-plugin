<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity;

use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\AdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserMagicLinkTokenRepository;

#[ORM\Entity(repositoryClass: AdminUserMagicLinkTokenRepository::class)]
#[ORM\Table(name: 'three_brs_admin_user_magic_link_token')]
#[ORM\UniqueConstraint(name: 'uniq_admin_user_magic_link_token_hash', columns: ['token_hash'])]
#[ORM\Index(name: 'idx_admin_user_magic_link_admin_user', columns: ['admin_user_id'])]
class AdminUserMagicLinkToken implements AdminUserMagicLinkTokenInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: AdminUserInterface::class)]
    #[ORM\JoinColumn(name: 'admin_user_id', referencedColumnName: 'id', onDelete: 'CASCADE', nullable: false)]
    private AdminUserInterface $adminUser;

    #[ORM\Column(name: 'token_hash', type: 'string', length: 64, nullable: false)]
    private string $tokenHash;

    #[ORM\Column(name: 'expires_at', type: 'datetime_immutable', nullable: false)]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(name: 'used_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $usedAt = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable', nullable: false)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'requested_ip', type: 'string', length: 45, nullable: true)]
    private ?string $requestedIp = null;

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

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function setTokenHash(string $tokenHash): void
    {
        $this->tokenHash = $tokenHash;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeImmutable $expiresAt): void
    {
        $this->expiresAt = $expiresAt;
    }

    public function getUsedAt(): ?\DateTimeImmutable
    {
        return $this->usedAt;
    }

    public function setUsedAt(?\DateTimeImmutable $usedAt): void
    {
        $this->usedAt = $usedAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getRequestedIp(): ?string
    {
        return $this->requestedIp;
    }

    public function setRequestedIp(?string $requestedIp): void
    {
        $this->requestedIp = $requestedIp;
    }
}
