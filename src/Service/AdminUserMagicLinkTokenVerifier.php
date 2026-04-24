<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service;

use Psr\Clock\ClockInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserMagicLinkTokenInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserMagicLinkTokenRepositoryInterface;

class AdminUserMagicLinkTokenVerifier implements AdminUserMagicLinkTokenVerifierInterface
{
    public function __construct(
        private AdminUserMagicLinkTokenRepositoryInterface $repository,
        private MagicLinkTokenGeneratorInterface $generator,
        private ClockInterface $clock,
    ) {
    }

    public function verify(string $plainToken): ?AdminUserMagicLinkTokenInterface
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
