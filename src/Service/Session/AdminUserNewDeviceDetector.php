<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserKnownDevice;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserKnownDeviceRepositoryInterface;

class AdminUserNewDeviceDetector implements AdminUserNewDeviceDetectorInterface
{
    public function __construct(
        protected AdminUserKnownDeviceRepositoryInterface $repository,
        protected EntityManagerInterface $entityManager,
    ) {
    }

    public function checkAndRemember(AdminUserInterface $user, string $fingerprint): bool
    {
        if ($this->repository->existsForAdminUser($user, $fingerprint)) {
            return false;
        }

        $device = new AdminUserKnownDevice();
        $device->setAdminUser($user);
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
