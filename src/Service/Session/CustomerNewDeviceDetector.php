<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
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

        try {
            $this->entityManager->persist($device);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            // Concurrent login from the same device raced ahead and persisted the
            // (user, fingerprint) row first. Treat as already-known so we don't fire
            // a duplicate "new device" notification email.
            $this->entityManager->detach($device);

            return false;
        }

        return true;
    }
}
