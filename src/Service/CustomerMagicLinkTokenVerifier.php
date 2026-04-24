<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service;

use Psr\Clock\ClockInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerMagicLinkTokenInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerMagicLinkTokenRepositoryInterface;

class CustomerMagicLinkTokenVerifier implements CustomerMagicLinkTokenVerifierInterface
{
    public function __construct(
        private CustomerMagicLinkTokenRepositoryInterface $repository,
        private MagicLinkTokenGeneratorInterface $generator,
        private ClockInterface $clock,
    ) {
    }

    public function verify(string $plainToken): ?CustomerMagicLinkTokenInterface
    {
        if ($plainToken === '') {
            return null;
        }

        $token = $this->repository->findOneByTokenHash($this->generator->hash($plainToken));
        if ($token === null) {
            return null;
        }

        if ($token->getUsedAt() !== null) {
            return null;
        }

        if ($token->getExpiresAt() < $this->clock->now()) {
            return null;
        }

        return $token;
    }
}
