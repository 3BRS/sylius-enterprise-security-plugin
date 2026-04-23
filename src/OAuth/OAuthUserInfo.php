<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\OAuth;

class OAuthUserInfo implements OAuthUserInfoInterface
{
    public function __construct(
        private readonly string $provider,
        private readonly string $providerUserId,
        private readonly ?string $email,
        private readonly ?string $firstName = null,
        private readonly ?string $lastName = null,
        private readonly ?bool $emailVerified = null,
    ) {
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getProviderUserId(): string
    {
        return $this->providerUserId;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function isEmailVerified(): ?bool
    {
        return $this->emailVerified;
    }
}
