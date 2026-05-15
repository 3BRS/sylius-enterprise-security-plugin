<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity;

use Doctrine\ORM\Mapping as ORM;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\SecuritySettingRepository;

#[ORM\Entity(repositoryClass: SecuritySettingRepository::class)]
#[ORM\Table(name: 'three_brs_security_setting')]
#[ORM\UniqueConstraint(
    name: 'uniq_three_brs_security_setting_path_scope',
    columns: ['path', 'scope'],
)]
class SecuritySetting implements SecuritySettingInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: 'integer')]
    protected ?int $id = null;

    #[ORM\Column(name: 'path', type: 'string', length: 191, nullable: false)]
    protected string $path;

    #[ORM\Column(name: 'scope', type: 'string', length: 20, nullable: false)]
    protected string $scope;

    #[ORM\Column(name: 'value', type: 'json', nullable: true)]
    protected mixed $value = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable', nullable: false)]
    protected \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable', nullable: false)]
    protected \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    public function getScope(): string
    {
        return $this->scope;
    }

    public function setScope(string $scope): void
    {
        $this->scope = $scope;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function setValue(mixed $value): void
    {
        $this->value = $value;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
