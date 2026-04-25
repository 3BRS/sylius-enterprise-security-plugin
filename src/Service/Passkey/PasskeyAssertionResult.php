<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Passkey;

class PasskeyAssertionResult implements PasskeyAssertionResultInterface
{
    public function __construct(
        protected readonly object $credential,
        protected readonly object $user,
        protected readonly bool $userVerified,
    ) {
    }

    public function getCredential(): object
    {
        return $this->credential;
    }

    public function getUser(): object
    {
        return $this->user;
    }

    public function isUserVerified(): bool
    {
        return $this->userVerified;
    }
}
