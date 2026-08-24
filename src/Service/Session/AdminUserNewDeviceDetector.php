<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use ThreeBRS\EnterpriseSecurityBundle\Session\AbstractNewDeviceDetector;
use ThreeBRS\EnterpriseSecurityBundle\Session\KnownDeviceRecordInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserKnownDevice;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserKnownDeviceRepositoryInterface;

class AdminUserNewDeviceDetector extends AbstractNewDeviceDetector implements AdminUserNewDeviceDetectorInterface
{
    public function __construct(
        protected AdminUserKnownDeviceRepositoryInterface $repository,
        protected EntityManagerInterface $entityManager,
    ) {
    }

    protected function isKnownDevice(UserInterface $user, string $fingerprint): bool
    {
        \assert($user instanceof AdminUserInterface);

        return $this->repository->existsForAdminUser($user, $fingerprint);
    }

    protected function createRecord(UserInterface $user, string $fingerprint): KnownDeviceRecordInterface
    {
        \assert($user instanceof AdminUserInterface);

        $device = new AdminUserKnownDevice();
        $device->setAdminUser($user);
        $device->setFingerprint($fingerprint);

        return $device;
    }

    protected function save(KnownDeviceRecordInterface $record): void
    {
        $this->entityManager->persist($record);
        $this->entityManager->flush();
    }

    protected function discardUnflushed(KnownDeviceRecordInterface $record): void
    {
        $this->entityManager->detach($record);
    }

    protected function isConcurrentInsertConflict(\Throwable $exception): bool
    {
        return $exception instanceof UniqueConstraintViolationException;
    }
}
