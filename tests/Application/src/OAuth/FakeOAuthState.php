<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\OAuth;

use ThreeBRS\EnterpriseSecurityBundle\OAuth\OAuthUserInfo;
use ThreeBRS\EnterpriseSecurityBundle\OAuth\OAuthUserInfoInterface;

class FakeOAuthState implements FakeOAuthStateInterface
{
    /** @var array<string, OAuthUserInfoInterface> */
    private static array $userInfoByProvider = [];

    public function seedUserInfo(
        string $provider,
        string $providerUserId,
        ?string $email,
        ?string $firstName = null,
        ?string $lastName = null,
    ): void {
        self::$userInfoByProvider[$provider] = new OAuthUserInfo(
            $provider,
            $providerUserId,
            $email,
            $firstName,
            $lastName,
        );
    }

    public function getUserInfo(string $provider): ?OAuthUserInfoInterface
    {
        return self::$userInfoByProvider[$provider] ?? null;
    }

    public function reset(): void
    {
        self::$userInfoByProvider = [];
    }
}
