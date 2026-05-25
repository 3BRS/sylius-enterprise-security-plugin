<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service;

use ThreeBRS\EnterpriseSecurityBundle\MagicLink\MagicLinkTokenGeneratorInterface;
use ThreeBRS\EnterpriseSecurityBundle\MagicLink\MagicLinkTokenValidatorInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserMagicLinkTokenInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserMagicLinkTokenRepositoryInterface;

class AdminUserMagicLinkTokenVerifier implements AdminUserMagicLinkTokenVerifierInterface
{
    public function __construct(
        protected AdminUserMagicLinkTokenRepositoryInterface $repository,
        protected MagicLinkTokenGeneratorInterface $generator,
        protected MagicLinkTokenValidatorInterface $validator,
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

        if (!$this->validator->isUsable($token)) {
            return null;
        }

        return $token;
    }
}
