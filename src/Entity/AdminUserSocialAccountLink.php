<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity;

use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\AdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserSocialAccountLinkRepository;

#[ORM\Entity(repositoryClass: AdminUserSocialAccountLinkRepository::class)]
#[ORM\Table(name: 'three_brs_admin_user_social_account_link')]
#[ORM\UniqueConstraint(name: 'uniq_admin_user_provider', columns: ['admin_user_id', 'provider'])]
#[ORM\UniqueConstraint(name: 'uniq_admin_provider_user', columns: ['provider', 'provider_user_id'])]
class AdminUserSocialAccountLink implements AdminUserSocialAccountLinkInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: 'integer')]
    protected ?int $id = null;

    #[ORM\ManyToOne(targetEntity: AdminUserInterface::class)]
    #[ORM\JoinColumn(name: 'admin_user_id', referencedColumnName: 'id', onDelete: 'CASCADE', nullable: false)]
    protected AdminUserInterface $adminUser;

    #[ORM\Column(name: 'provider', type: 'string', length: 32, nullable: false)]
    protected string $provider;

    #[ORM\Column(name: 'provider_user_id', type: 'string', length: 255, nullable: false)]
    protected string $providerUserId;

    #[ORM\Column(name: 'email', type: 'string', length: 255, nullable: true)]
    protected ?string $email = null;

    #[ORM\Column(name: 'linked_at', type: 'datetime_immutable', nullable: false)]
    protected \DateTimeImmutable $linkedAt;

    #[ORM\Column(name: 'last_used_at', type: 'datetime_immutable', nullable: true)]
    protected ?\DateTimeImmutable $lastUsedAt = null;

    public function __construct()
    {
        $this->linkedAt = new \DateTimeImmutable();
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

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): void
    {
        $this->provider = $provider;
    }

    public function getProviderUserId(): string
    {
        return $this->providerUserId;
    }

    public function setProviderUserId(string $providerUserId): void
    {
        $this->providerUserId = $providerUserId;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }

    public function getLinkedAt(): \DateTimeImmutable
    {
        return $this->linkedAt;
    }

    public function getLastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function setLastUsedAt(?\DateTimeImmutable $lastUsedAt): void
    {
        $this->lastUsedAt = $lastUsedAt;
    }
}
