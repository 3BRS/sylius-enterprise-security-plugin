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

    public function request(string $email, ?string $ip): void
    {
        if (!$this->enabled) {
            return;
        }

        if ($email === '') {
            return;
        }

        $user = $this->findUserByEmail($email);
        if ($user === null) {
            return;
        }

        $now = $this->clock->now();
        $windowStart = $now->sub(new \DateInterval('PT' . $this->rateLimitWindowSeconds . 'S'));
        if ($this->tokenRepository->countRecentForAdminUser($user, $windowStart) >= $this->rateLimitMax) {
            return;
        }

        $plainToken = $this->tokenGenerator->generatePlainToken();

        $token = new AdminUserMagicLinkToken();
        $token->setAdminUser($user);
        $token->setTokenHash($this->tokenGenerator->hash($plainToken));
        $token->setExpiresAt($now->add(new \DateInterval('PT' . $this->expirationSeconds . 'S')));
        $token->setRequestedIp($ip);

        $this->entityManager->persist($token);
        $this->entityManager->flush();

        $this->emailManager->sendMagicLink($user, $plainToken, $this->expirationSeconds);
    }

    private function findUserByEmail(string $email): ?AdminUserInterface
    {
        $user = $this->adminUserRepository->findOneBy(['emailCanonical' => strtolower($email)]);

        return $user instanceof AdminUserInterface ? $user : null;
    }
}
