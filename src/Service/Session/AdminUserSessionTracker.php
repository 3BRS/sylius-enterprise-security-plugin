<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserSession;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserSessionInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserSessionRepositoryInterface;

class AdminUserSessionTracker implements AdminUserSessionTrackerInterface
{
    protected const ACTIVITY_TOUCH_THROTTLE_SECONDS = 60;

    public function __construct(
        protected AdminUserSessionRepositoryInterface $repository,
        protected EntityManagerInterface $entityManager,
        protected GeoIpLookupInterface $geoIpLookup,
        protected ClockInterface $clock,
    ) {
    }

    public function track(
        AdminUserInterface $user,
        string $sessionId,
        ?string $userAgent,
        ?string $ipAddress,
    ): AdminUserSessionInterface {
        $existing = $this->repository->findOneBySessionId($sessionId);
        if ($existing !== null) {
            return $existing;
        }

        $geo = $this->geoIpLookup->lookup($ipAddress);

        $session = new AdminUserSession();
        $session->setAdminUser($user);
        $session->setSessionId($sessionId);
        $session->setUserAgent($userAgent);
        $session->setIpAddress($ipAddress);
        $session->setCountry($geo?->countryCode);
        $session->setCity($geo?->city);

        $this->entityManager->persist($session);
        $this->entityManager->flush();

        return $session;
    }

    public function touch(string $sessionId): void
    {
        $session = $this->repository->findOneBySessionId($sessionId);
        if ($session === null || $session->isRevoked()) {
            return;
        }

        $now = $this->clock->now();
        $diff = $now->getTimestamp() - $session->getLastActivityAt()->getTimestamp();
        if ($diff < self::ACTIVITY_TOUCH_THROTTLE_SECONDS) {
            return;
        }

        $session->setLastActivityAt($now);
        $this->entityManager->flush();
    }

    public function revoke(AdminUserSessionInterface $session): void
    {
        if ($session->isRevoked()) {
            return;
        }

        $session->setRevokedAt($this->clock->now());
        $this->entityManager->flush();
    }

    public function revokeOthers(string $currentSessionId, AdminUserInterface $user): void
    {
        $now = $this->clock->now();
        foreach ($this->repository->findActiveForAdminUser($user) as $session) {
            if ($session->getSessionId() === $currentSessionId) {
                continue;
            }
            $session->setRevokedAt($now);
        }
        $this->entityManager->flush();
    }
}
