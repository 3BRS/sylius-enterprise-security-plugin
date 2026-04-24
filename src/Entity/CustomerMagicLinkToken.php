<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity;

use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerMagicLinkTokenRepository;

#[ORM\Entity(repositoryClass: CustomerMagicLinkTokenRepository::class)]
#[ORM\Table(name: 'three_brs_customer_magic_link_token')]
#[ORM\UniqueConstraint(name: 'uniq_customer_magic_link_token_hash', columns: ['token_hash'])]
#[ORM\Index(name: 'idx_customer_magic_link_shop_user', columns: ['shop_user_id'])]
class CustomerMagicLinkToken implements CustomerMagicLinkTokenInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ShopUserInterface::class)]
    #[ORM\JoinColumn(name: 'shop_user_id', referencedColumnName: 'id', onDelete: 'CASCADE', nullable: false)]
    private ShopUserInterface $shopUser;

    #[ORM\Column(name: 'token_hash', type: 'string', length: 64, nullable: false)]
    private string $tokenHash;

    #[ORM\Column(name: 'expires_at', type: 'datetime_immutable', nullable: false)]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(name: 'used_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $usedAt = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable', nullable: false)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
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
}
