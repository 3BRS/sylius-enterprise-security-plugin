<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserMagicLinkToken;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer\AdminUserMagicLinkEmailManagerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserMagicLinkTokenRepositoryInterface;

class AdminMagicLinkRequestHandler implements AdminMagicLinkRequestHandlerInterface
{
    /**
     * @param UserRepositoryInterface<AdminUserInterface> $adminUserRepository
     */
    public function __construct(
        private UserRepositoryInterface $adminUserRepository,
        private AdminUserMagicLinkTokenRepositoryInterface $tokenRepository,
        private MagicLinkTokenGeneratorInterface $tokenGenerator,
        private AdminUserMagicLinkEmailManagerInterface $emailManager,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
        private bool $enabled,
        private int $expirationSeconds,
        private int $rateLimitMax,
        private int $rateLimitWindowSeconds,
    ) {
    }

    public function request(string $email): void
    {
        if (!$this->enabled) {
            return;
        }

        // Generate and hash token regardless of whether the email is known —
        // keeps the CPU work constant so response time does not leak account existence.
        $plainToken = $this->tokenGenerator->generatePlainToken();
        $tokenHash = $this->tokenGenerator->hash($plainToken);
        $now = $this->clock->now();

        $user = $this->findUserByEmail($email);
        if ($user === null) {
            return;
        }

        $windowStart = $now->sub(new \DateInterval('PT' . $this->rateLimitWindowSeconds . 'S'));
        if ($this->tokenRepository->countRecentForAdminUser($user, $windowStart) >= $this->rateLimitMax) {
            return;
        }

        $token = new AdminUserMagicLinkToken();
        $token->setAdminUser($user);
        $token->setTokenHash($tokenHash);
        $token->setExpiresAt($now->add(new \DateInterval('PT' . $this->expirationSeconds . 'S')));

        $this->entityManager->persist($token);
        $this->entityManager->flush();

        $this->emailManager->sendMagicLink($user, $plainToken, $this->expirationSeconds);
    }

    private function findUserByEmail(string $email): ?AdminUserInterface
    {
        if ($email === '') {
            return null;
        }

        $user = $this->adminUserRepository->findOneBy(['emailCanonical' => strtolower($email)]);

        return $user instanceof AdminUserInterface ? $user : null;
    }
}
