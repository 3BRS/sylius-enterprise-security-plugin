<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use ThreeBRS\EnterpriseSecurityBundle\Session\AbstractSessionTracker;
use ThreeBRS\EnterpriseSecurityBundle\Session\GeoIp\GeoIpLookupInterface;
use ThreeBRS\EnterpriseSecurityBundle\Session\SessionRecordInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserSession;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserSessionInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserSessionRepositoryInterface;

class AdminUserSessionTracker extends AbstractSessionTracker implements AdminUserSessionTrackerInterface
{
    public function __construct(
        protected AdminUserSessionRepositoryInterface $repository,
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
    ): AdminUserSessionInterface {
        $result = parent::track($user, $sessionId, $userAgent, $ipAddress);
        \assert($result instanceof AdminUserSessionInterface);

        return $result;
    }

    protected function findOneBySessionId(string $sessionId): ?SessionRecordInterface
    {
        return $this->repository->findOneBySessionId($sessionId);
    }

    protected function findActiveForUser(UserInterface $user): iterable
    {
        if (!$user instanceof AdminUserInterface) {
            return [];
        }

        return $this->repository->findActiveForAdminUser($user);
    }

    protected function createNewRecord(
        UserInterface $user,
        string $sessionId,
        ?string $userAgent,
        ?string $ipAddress,
        ?string $country,
        ?string $city,
    ): SessionRecordInterface {
        \assert($user instanceof AdminUserInterface);

        $session = new AdminUserSession();
        $session->setAdminUser($user);
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
