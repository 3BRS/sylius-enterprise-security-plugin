<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\OAuth;

use Symfony\Component\HttpFoundation\Request;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\OAuth\Exception\OAuthProviderException;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\OAuth\OAuthProviderInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\OAuth\OAuthUserInfoInterface;

class FakeOAuthProvider implements OAuthProviderInterface
{
    public function __construct(
        private string $name,
        private FakeOAuthStateInterface $state,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isEnabledForCustomer(): bool
    {
        return true;
    }

    public function isEnabledForAdmin(): bool
    {
        return true;
    }

    public function getAuthorizationUrl(string $redirectUri, string $state, string $group): string
    {
        $separator = str_contains($redirectUri, '?') ? '&' : '?';

        return $redirectUri . $separator . http_build_query([
            'code' => 'fake-auth-code',
            'state' => $state,
        ]);
    }

    public function fetchUserInfo(Request $request, string $redirectUri, string $expectedState, string $group): OAuthUserInfoInterface
    {
        $state = (string) ($request->query->get('state') ?? $request->request->get('state', ''));
        if ($state === '' || !hash_equals($expectedState, $state)) {
            throw new OAuthProviderException('Invalid OAuth state parameter.');
        }

        $info = $this->state->getUserInfo($this->name);
        if ($info === null) {
            throw new OAuthProviderException(sprintf('No fake user info seeded for provider "%s".', $this->name));
        }

        return $info;
    }
}
