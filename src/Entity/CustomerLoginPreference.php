<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity;

use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerLoginPreferenceRepository;

#[ORM\Entity(repositoryClass: CustomerLoginPreferenceRepository::class)]
#[ORM\Table(name: 'three_brs_customer_login_preference')]
#[ORM\UniqueConstraint(name: 'uniq_customer_login_preference_shop_user', columns: ['shop_user_id'])]
class CustomerLoginPreference implements CustomerLoginPreferenceInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: 'integer')]
    protected ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ShopUserInterface::class)]
    #[ORM\JoinColumn(name: 'shop_user_id', referencedColumnName: 'id', onDelete: 'CASCADE', nullable: false)]
    protected ShopUserInterface $shopUser;

    #[ORM\Column(name: 'password_login_allowed', type: 'boolean', nullable: false, options: ['default' => true])]
    protected bool $passwordLoginAllowed = true;

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

    public function isPasswordLoginAllowed(): bool
    {
        return $this->passwordLoginAllowed;
    }

    public function setPasswordLoginAllowed(bool $allowed): void
    {
        $this->passwordLoginAllowed = $allowed;
    }
}
