<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\OAuth;

use ThreeBRS\EnterpriseSecurityBundle\OAuth\OAuthUserInfoInterface;

interface FakeOAuthStateInterface
{
    public function seedUserInfo(
        string $provider,
        string $providerUserId,
        ?string $email,
        ?string $firstName = null,
        ?string $lastName = null,
    ): void;

    public function getUserInfo(string $provider): ?OAuthUserInfoInterface;

    public function reset(): void;
}
