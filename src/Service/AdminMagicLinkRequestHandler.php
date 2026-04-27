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
        protected UserRepositoryInterface $adminUserRepository,
        protected AdminUserMagicLinkTokenRepositoryInterface $tokenRepository,
        protected MagicLinkTokenGeneratorInterface $tokenGenerator,
        protected AdminUserMagicLinkEmailManagerInterface $emailManager,
        protected EntityManagerInterface $entityManager,
        protected ClockInterface $clock,
        protected TimingPaddingInterface $timingPadding,
        protected bool $enabled,
        protected int $expirationSeconds,
    ) {
    }

    public function request(string $email): void
    {
        if (!$this->enabled) {
            return;
        }

        // Pad every code path to the same wall-clock deadline so an attacker
        // cannot tell whether the email exists, was rate-limited, or got the
        // full happy path (DB write + SMTP send) by measuring response time.
        $startedAt = microtime(true);

        try {
            $plainToken = $this->tokenGenerator->generatePlainToken();
            $tokenHash = $this->tokenGenerator->hash($plainToken);
            $now = $this->clock->now();

            $user = $this->findUserByEmail($email);
            if ($user === null) {
                return;
            }

            $token = new AdminUserMagicLinkToken();
            $token->setAdminUser($user);
            $token->setTokenHash($tokenHash);
            $token->setExpiresAt($now->add(new \DateInterval('PT' . $this->expirationSeconds . 'S')));

            $this->entityManager->persist($token);
            $this->entityManager->flush();

            $this->emailManager->sendMagicLink($user, $plainToken, $this->expirationSeconds);
        } finally {
            $this->timingPadding->padTo($startedAt);
        }
    }

    protected function findUserByEmail(string $email): ?AdminUserInterface
    {
        if ($email === '') {
            return null;
        }

        $user = $this->adminUserRepository->findOneBy(['emailCanonical' => strtolower($email)]);

        return $user instanceof AdminUserInterface ? $user : null;
    }
}
