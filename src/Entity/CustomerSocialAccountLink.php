<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity;

use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerSocialAccountLinkRepository;

#[ORM\Entity(repositoryClass: CustomerSocialAccountLinkRepository::class)]
#[ORM\Table(name: 'three_brs_customer_social_account_link')]
#[ORM\UniqueConstraint(name: 'uniq_customer_provider', columns: ['shop_user_id', 'provider'])]
#[ORM\UniqueConstraint(name: 'uniq_customer_provider_user', columns: ['provider', 'provider_user_id'])]
class CustomerSocialAccountLink implements CustomerSocialAccountLinkInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: 'integer')]
    protected ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ShopUserInterface::class)]
    #[ORM\JoinColumn(name: 'shop_user_id', referencedColumnName: 'id', onDelete: 'CASCADE', nullable: false)]
    protected ShopUserInterface $shopUser;

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

    public function getShopUser(): ShopUserInterface
    {
        return $this->shopUser;
    }

    public function setShopUser(ShopUserInterface $shopUser): void
    {
        $this->shopUser = $shopUser;
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
