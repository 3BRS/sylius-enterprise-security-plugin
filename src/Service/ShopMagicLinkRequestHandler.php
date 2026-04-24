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
        private CustomerRepositoryInterface $customerRepository,
        private CustomerMagicLinkTokenRepositoryInterface $tokenRepository,
        private MagicLinkTokenGeneratorInterface $tokenGenerator,
        private CustomerMagicLinkEmailManagerInterface $emailManager,
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
        if ($this->tokenRepository->countRecentForShopUser($user, $windowStart) >= $this->rateLimitMax) {
            return;
        }

        $plainToken = $this->tokenGenerator->generatePlainToken();

        $token = new CustomerMagicLinkToken();
        $token->setShopUser($user);
        $token->setTokenHash($this->tokenGenerator->hash($plainToken));
        $token->setExpiresAt($now->add(new \DateInterval('PT' . $this->expirationSeconds . 'S')));
        $token->setRequestedIp($ip);

        $this->entityManager->persist($token);
        $this->entityManager->flush();

        $this->emailManager->sendMagicLink($user, $plainToken, $this->expirationSeconds);
    }

    private function findUserByEmail(string $email): ?ShopUserInterface
    {
        $customer = $this->customerRepository->findOneBy(['email' => $email]);
        if (!$customer instanceof CustomerInterface) {
            return null;
        }

        $user = $customer->getUser();

        return $user instanceof ShopUserInterface ? $user : null;
    }
}
