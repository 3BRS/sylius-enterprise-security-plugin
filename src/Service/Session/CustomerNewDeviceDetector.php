<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerKnownDevice;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerKnownDeviceRepositoryInterface;

class CustomerNewDeviceDetector implements CustomerNewDeviceDetectorInterface
{
    public function __construct(
        protected CustomerKnownDeviceRepositoryInterface $repository,
        protected EntityManagerInterface $entityManager,
    ) {
    }

    public function checkAndRemember(ShopUserInterface $user, string $fingerprint): bool
    {
        if ($this->repository->existsForShopUser($user, $fingerprint)) {
            return false;
        }

        $device = new CustomerKnownDevice();
        $device->setShopUser($user);
        $device->setFingerprint($fingerprint);

        $this->entityManager->persist($device);
        $this->entityManager->flush();

        return true;
    }
}
