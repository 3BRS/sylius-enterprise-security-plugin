<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity;

use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerDeletionRequestRepository;

#[ORM\Entity(repositoryClass: CustomerDeletionRequestRepository::class)]
#[ORM\Table(name: 'three_brs_customer_deletion_request')]
#[ORM\Index(name: 'idx_three_brs_customer_deletion_request_due', columns: ['scheduled_for'])]
class CustomerDeletionRequest implements CustomerDeletionRequestInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: 'integer')]
    protected ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CustomerInterface::class)]
    #[ORM\JoinColumn(name: 'customer_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    protected CustomerInterface $customer;

    #[ORM\Column(name: 'requested_at', type: 'datetime_immutable', nullable: false)]
    protected \DateTimeImmutable $requestedAt;

    #[ORM\Column(name: 'scheduled_for', type: 'datetime_immutable', nullable: false)]
    protected \DateTimeImmutable $scheduledFor;

    #[ORM\Column(name: 'cancelled_at', type: 'datetime_immutable', nullable: true)]
    protected ?\DateTimeImmutable $cancelledAt = null;

    #[ORM\ManyToOne(targetEntity: AdminUserInterface::class)]
    #[ORM\JoinColumn(name: 'cancelled_by_admin_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    protected ?AdminUserInterface $cancelledByAdmin = null;

    #[ORM\Column(name: 'completed_at', type: 'datetime_immutable', nullable: true)]
    protected ?\DateTimeImmutable $completedAt = null;

    public function __construct()
    {
        $this->requestedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCustomer(): CustomerInterface
    {
        return $this->customer;
    }

    public function setCustomer(CustomerInterface $customer): void
    {
        $this->customer = $customer;
    }

    public function getRequestedAt(): \DateTimeImmutable
    {
        return $this->requestedAt;
    }

    public function getScheduledFor(): \DateTimeImmutable
    {
        return $this->scheduledFor;
    }

    public function setScheduledFor(\DateTimeImmutable $scheduledFor): void
    {
        $this->scheduledFor = $scheduledFor;
    }

    public function getCancelledAt(): ?\DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function setCancelledAt(?\DateTimeImmutable $cancelledAt): void
    {
        $this->cancelledAt = $cancelledAt;
    }

    public function getCancelledByAdmin(): ?AdminUserInterface
    {
        return $this->cancelledByAdmin;
    }

    public function setCancelledByAdmin(?AdminUserInterface $admin): void
    {
        $this->cancelledByAdmin = $admin;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeImmutable $completedAt): void
    {
        $this->completedAt = $completedAt;
    }

    public function isPending(): bool
    {
        return $this->cancelledAt === null && $this->completedAt === null;
    }
}
