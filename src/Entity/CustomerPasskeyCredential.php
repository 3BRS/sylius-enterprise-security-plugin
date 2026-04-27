<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity;

use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerPasskeyCredentialRepository;

#[ORM\Entity(repositoryClass: CustomerPasskeyCredentialRepository::class)]
#[ORM\Table(name: 'three_brs_customer_passkey_credential')]
#[ORM\UniqueConstraint(name: 'uniq_customer_passkey_credential_id', columns: ['credential_id'])]
#[ORM\Index(name: 'idx_customer_passkey_shop_user', columns: ['shop_user_id'])]
class CustomerPasskeyCredential implements CustomerPasskeyCredentialInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: 'integer')]
    protected ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ShopUserInterface::class)]
    #[ORM\JoinColumn(name: 'shop_user_id', referencedColumnName: 'id', onDelete: 'CASCADE', nullable: false)]
    protected ShopUserInterface $shopUser;

    /** @var resource|string */
    #[ORM\Column(name: 'credential_id', type: 'binary', length: 1024, nullable: false)]
    protected $credentialId;

    /** @var array<string, mixed> */
    #[ORM\Column(name: 'credential_source', type: 'json', nullable: false)]
    protected array $credentialSource;

    #[ORM\Column(name: 'label', type: 'string', length: 64, nullable: false)]
    protected string $label;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable', nullable: false)]
    protected \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'last_used_at', type: 'datetime_immutable', nullable: true)]
    protected ?\DateTimeImmutable $lastUsedAt = null;

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

    public function getCredentialId(): string
    {
        if (is_resource($this->credentialId)) {
            $this->credentialId = (string) stream_get_contents($this->credentialId);
        }

        return (string) $this->credentialId;
    }

    public function setCredentialId(string $credentialId): void
    {
        $this->credentialId = $credentialId;
    }

    /** @return array<string, mixed> */
    public function getCredentialSource(): array
    {
        return $this->credentialSource;
    }

    /** @param array<string, mixed> $credentialSource */
    public function setCredentialSource(array $credentialSource): void
    {
        $this->credentialSource = $credentialSource;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
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
