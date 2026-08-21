<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity;

use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\AdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserSessionRepository;

#[ORM\Entity(repositoryClass: AdminUserSessionRepository::class)]
#[ORM\Table(name: 'three_brs_admin_user_session')]
#[ORM\UniqueConstraint(name: 'uniq_admin_user_session_session_id', columns: ['session_id'])]
#[ORM\Index(name: 'idx_admin_user_session_admin_user', columns: ['admin_user_id'])]
class AdminUserSession implements AdminUserSessionInterface
{
    /** Matches the `user_agent` column width below; `static::` so a subclass can widen both together. */
    protected const USER_AGENT_MAX_LENGTH = 1024;

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: 'integer')]
    protected ?int $id = null;

    #[ORM\ManyToOne(targetEntity: AdminUserInterface::class)]
    #[ORM\JoinColumn(name: 'admin_user_id', referencedColumnName: 'id', onDelete: 'CASCADE', nullable: false)]
    protected AdminUserInterface $adminUser;

    #[ORM\Column(name: 'session_id', type: 'string', length: 128, nullable: false)]
    protected string $sessionId;

    #[ORM\Column(name: 'user_agent', type: 'string', length: 1024, nullable: true)]
    protected ?string $userAgent = null;

    #[ORM\Column(name: 'ip_address', type: 'string', length: 45, nullable: true)]
    protected ?string $ipAddress = null;

    #[ORM\Column(name: 'country', type: 'string', length: 2, nullable: true)]
    protected ?string $country = null;

    #[ORM\Column(name: 'city', type: 'string', length: 128, nullable: true)]
    protected ?string $city = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable', nullable: false)]
    protected \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'last_activity_at', type: 'datetime_immutable', nullable: false)]
    protected \DateTimeImmutable $lastActivityAt;

    #[ORM\Column(name: 'revoked_at', type: 'datetime_immutable', nullable: true)]
    protected ?\DateTimeImmutable $revokedAt = null;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->lastActivityAt = $now;
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

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function setSessionId(string $sessionId): void
    {
        $this->sessionId = $sessionId;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): void
    {
        // Truncated to the column width here rather than at the call site: the
        // header arrives unbounded from the client and every writer would
        // otherwise have to remember the limit this entity owns.
        $this->userAgent = $userAgent === null
            ? null
            : mb_substr($userAgent, 0, static::USER_AGENT_MAX_LENGTH);
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): void
    {
        $this->ipAddress = $ipAddress;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): void
    {
        $this->country = $country;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): void
    {
        $this->city = $city;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastActivityAt(): \DateTimeImmutable
    {
        return $this->lastActivityAt;
    }

    public function setLastActivityAt(\DateTimeImmutable $lastActivityAt): void
    {
        $this->lastActivityAt = $lastActivityAt;
    }

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function setRevokedAt(?\DateTimeImmutable $revokedAt): void
    {
        $this->revokedAt = $revokedAt;
    }

    public function isRevoked(): bool
    {
        return $this->revokedAt !== null;
    }
}
