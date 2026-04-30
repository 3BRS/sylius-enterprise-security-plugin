<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerSession;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerSessionInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerSessionRepositoryInterface;

class CustomerSessionTracker implements CustomerSessionTrackerInterface
{
    protected const ACTIVITY_TOUCH_THROTTLE_SECONDS = 60;

    public function __construct(
        protected CustomerSessionRepositoryInterface $repository,
        protected EntityManagerInterface $entityManager,
        protected GeoIpLookupInterface $geoIpLookup,
        protected ClockInterface $clock,
    ) {
    }

    public function track(
        ShopUserInterface $user,
        string $sessionId,
        ?string $userAgent,
        ?string $ipAddress,
    ): CustomerSessionInterface {
        $existing = $this->repository->findOneBySessionId($sessionId);
        if ($existing !== null) {
            return $existing;
        }

        $geo = $this->geoIpLookup->lookup($ipAddress);

        $session = new CustomerSession();
        $session->setShopUser($user);
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

    public function revoke(CustomerSessionInterface $session): void
    {
        if ($session->isRevoked()) {
            return;
        }

        $session->setRevokedAt($this->clock->now());
        $this->entityManager->flush();
    }

    public function revokeOthers(string $currentSessionId, ShopUserInterface $user): void
    {
        $now = $this->clock->now();
        foreach ($this->repository->findActiveForShopUser($user) as $session) {
            if ($session->getSessionId() === $currentSessionId) {
                continue;
            }
            $session->setRevokedAt($now);
        }
        $this->entityManager->flush();
    }
}
