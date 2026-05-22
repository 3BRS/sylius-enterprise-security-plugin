<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use ThreeBRS\EnterpriseSecurityBundle\Session\AbstractSessionTracker;
use ThreeBRS\EnterpriseSecurityBundle\Session\GeoIp\GeoIpLookupInterface;
use ThreeBRS\EnterpriseSecurityBundle\Session\SessionRecordInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerSession;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerSessionInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerSessionRepositoryInterface;

class CustomerSessionTracker extends AbstractSessionTracker implements CustomerSessionTrackerInterface
{
    public function __construct(
        protected CustomerSessionRepositoryInterface $repository,
        protected EntityManagerInterface $entityManager,
        GeoIpLookupInterface $geoIpLookup,
        ClockInterface $clock,
    ) {
        parent::__construct($geoIpLookup, $clock);
    }

    public function track(
        UserInterface $user,
        string $sessionId,
        ?string $userAgent,
        ?string $ipAddress,
    ): CustomerSessionInterface {
        $result = parent::track($user, $sessionId, $userAgent, $ipAddress);
        \assert($result instanceof CustomerSessionInterface);

        return $result;
    }

    public function revokeAll(ShopUserInterface $user): void
    {
        // Customer-specific bulk revoke — used when an admin blocks a customer
        // account or the customer triggers it from session management UI.
        $now = $this->clock->now();
        foreach ($this->repository->findActiveForShopUser($user) as $session) {
            $session->setRevokedAt($now);
        }
        $this->commit();
    }

    protected function findOneBySessionId(string $sessionId): ?SessionRecordInterface
    {
        return $this->repository->findOneBySessionId($sessionId);
    }

    protected function findActiveForUser(UserInterface $user): iterable
    {
        if (!$user instanceof ShopUserInterface) {
            return [];
        }

        return $this->repository->findActiveForShopUser($user);
    }

    protected function createNewRecord(
        UserInterface $user,
        string $sessionId,
        ?string $userAgent,
        ?string $ipAddress,
        ?string $country,
        ?string $city,
    ): SessionRecordInterface {
        \assert($user instanceof ShopUserInterface);

        $session = new CustomerSession();
        $session->setShopUser($user);
        $session->setSessionId($sessionId);
        $session->setUserAgent($userAgent);
        $session->setIpAddress($ipAddress);
        $session->setCountry($country);
        $session->setCity($city);

        return $session;
    }

    protected function save(SessionRecordInterface $record): void
    {
        $this->entityManager->persist($record);
        $this->entityManager->flush();
    }

    protected function discardUnflushed(SessionRecordInterface $record): void
    {
        $this->entityManager->detach($record);
    }

    protected function commit(): void
    {
        $this->entityManager->flush();
    }

    protected function isConcurrentInsertConflict(\Throwable $exception): bool
    {
        return $exception instanceof UniqueConstraintViolationException;
    }
}
