<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerMagicLinkToken;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer\CustomerMagicLinkEmailManagerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerMagicLinkTokenRepositoryInterface;

class ShopMagicLinkRequestHandler implements ShopMagicLinkRequestHandlerInterface
{
    /**
     * @param CustomerRepositoryInterface<CustomerInterface> $customerRepository
     */
    public function __construct(
        protected CustomerRepositoryInterface $customerRepository,
        protected CustomerMagicLinkTokenRepositoryInterface $tokenRepository,
        protected MagicLinkTokenGeneratorInterface $tokenGenerator,
        protected CustomerMagicLinkEmailManagerInterface $emailManager,
        protected EntityManagerInterface $entityManager,
        protected ClockInterface $clock,
        protected TimingPaddingInterface $timingPadding,
        protected bool $enabled,
        protected int $expirationSeconds,
        protected int $rateLimitMax,
        protected int $rateLimitWindowSeconds,
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

            $windowStart = $now->sub(new \DateInterval('PT' . $this->rateLimitWindowSeconds . 'S'));
            if ($this->tokenRepository->countRecentForShopUser($user, $windowStart) >= $this->rateLimitMax) {
                return;
            }

            $token = new CustomerMagicLinkToken();
            $token->setShopUser($user);
            $token->setTokenHash($tokenHash);
            $token->setExpiresAt($now->add(new \DateInterval('PT' . $this->expirationSeconds . 'S')));

            $this->entityManager->persist($token);
            $this->entityManager->flush();

            $this->emailManager->sendMagicLink($user, $plainToken, $this->expirationSeconds);
        } finally {
            $this->timingPadding->padTo($startedAt);
        }
    }

    protected function findUserByEmail(string $email): ?ShopUserInterface
    {
        if ($email === '') {
            return null;
        }

        $customer = $this->customerRepository->findOneBy(['emailCanonical' => strtolower($email)]);
        if (!$customer instanceof CustomerInterface) {
            return null;
        }

        $user = $customer->getUser();

        return $user instanceof ShopUserInterface ? $user : null;
    }
}
