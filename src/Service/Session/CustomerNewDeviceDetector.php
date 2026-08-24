<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use ThreeBRS\EnterpriseSecurityBundle\Session\AbstractNewDeviceDetector;
use ThreeBRS\EnterpriseSecurityBundle\Session\KnownDeviceRecordInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerKnownDevice;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerKnownDeviceRepositoryInterface;

class CustomerNewDeviceDetector extends AbstractNewDeviceDetector implements CustomerNewDeviceDetectorInterface
{
    public function __construct(
        protected CustomerKnownDeviceRepositoryInterface $repository,
        protected EntityManagerInterface $entityManager,
    ) {
    }

    protected function isKnownDevice(UserInterface $user, string $fingerprint): bool
    {
        \assert($user instanceof ShopUserInterface);

        return $this->repository->existsForShopUser($user, $fingerprint);
    }

    protected function createRecord(UserInterface $user, string $fingerprint): KnownDeviceRecordInterface
    {
        \assert($user instanceof ShopUserInterface);

        $device = new CustomerKnownDevice();
        $device->setShopUser($user);
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
