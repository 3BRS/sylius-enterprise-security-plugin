<?php

declare(strict_types=1);

namespace ThreeBRS\EnterpriseSecurityBundle\OAuth;

use League\OAuth2\Client\Token\AccessToken;
use Symfony\Component\HttpFoundation\Request;
use TheNetworg\OAuth2\Client\Provider\Azure;
use TheNetworg\OAuth2\Client\Provider\AzureResourceOwner;
use ThreeBRS\EnterpriseSecurityBundle\OAuth\Exception\OAuthProviderException;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsProviderInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;

class MicrosoftOAuthProvider implements MicrosoftOAuthProviderInterface
{
    public const NAME = 'microsoft';

    public function __construct(
        protected SettingsProviderInterface $settings,
        protected ?string $customerClientId,
        protected ?string $customerClientSecret,
        protected ?string $customerTenant,
        protected ?string $adminClientId,
        protected ?string $adminClientSecret,
        protected ?string $adminTenant,
    ) {
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function isEnabledForCustomer(): bool
    {
        return $this->settings->getBool('oauth.microsoft.enabled', SettingsScope::CUSTOMER) &&
            $this->customerClientId !== null && $this->customerClientId !== '' &&
            $this->customerClientSecret !== null && $this->customerClientSecret !== '' &&
            $this->customerTenant !== null && $this->customerTenant !== '';
    }

    public function isEnabledForAdmin(): bool
    {
        return $this->settings->getBool('oauth.microsoft.enabled', SettingsScope::ADMIN) &&
            $this->adminClientId !== null && $this->adminClientId !== '' &&
            $this->adminClientSecret !== null && $this->adminClientSecret !== '' &&
            $this->adminTenant !== null && $this->adminTenant !== '';
    }

    public function getAuthorizationUrl(string $redirectUri, string $state, string $group): string
    {
        $this->assertGroup($group);
        $client = $this->buildClient($group, $redirectUri);

        return $client->getAuthorizationUrl([
            'scope' => ['openid', 'profile', 'email', 'User.Read'],
            'state' => $state,
            'prompt' => 'select_account',
        ]);
    }

    public function fetchUserInfo(Request $request, string $redirectUri, string $expectedState, string $group): OAuthUserInfoInterface
    {
        $this->assertGroup($group);

        $state = (string) $request->query->get('state');
        if ($state === '' || ! hash_equals($expectedState, $state)) {
            throw new OAuthProviderException('Invalid OAuth state parameter.');
        }

        $code = (string) $request->query->get('code');
        if ($code === '') {
            throw new OAuthProviderException('Missing authorization code in Microsoft callback.');
        }

        $client = $this->buildClient($group, $redirectUri);

        try {
            /** @var AccessToken $token */
            $token = $client->getAccessToken('authorization_code', [
                'code' => $code,
            ]);
            /** @var AzureResourceOwner $resourceOwner */
            $resourceOwner = $client->getResourceOwner($token);
        } catch (\Throwable $exception) {
            throw new OAuthProviderException('Failed to fetch Microsoft user info: ' . $exception->getMessage(), 0, $exception);
        }

        $raw = $resourceOwner->toArray();

        // Microsoft Graph v2.0 returns `mail` (primary email, nullable) and `userPrincipalName` (almost
        // always present, but for personal accounts can be a non-routable `*#EXT#@*.onmicrosoft.com` form).
        // Prefer `mail`; fall back to `upn` only when it looks like a real email.
        $email = isset($raw['mail']) && is_string($raw['mail']) && $raw['mail'] !== ''
            ? $raw['mail']
            : null;
        if ($email === null && isset($raw['userPrincipalName']) && is_string($raw['userPrincipalName'])) {
            $upn = $raw['userPrincipalName'];
            if ($upn !== '' && ! str_contains($upn, '#EXT#')) {
                $email = $upn;
            }
        }

        $firstName = isset($raw['givenName']) && is_string($raw['givenName']) ? $raw['givenName'] : null;
        $lastName = isset($raw['surname']) && is_string($raw['surname']) ? $raw['surname'] : null;

        // Microsoft does not include a standard `email_verified` claim for personal accounts
        // — leave it null so AutoRegistrationPolicy treats it as "unknown" rather than "false".
        return new OAuthUserInfo(
            self::NAME,
            (string) $resourceOwner->getId(),
            $email,
            $firstName,
            $lastName,
            null,
        );
    }

    protected function assertGroup(string $group): void
    {
        if (! in_array($group, ['customer', 'admin'], true)) {
            throw new OAuthProviderException('Group must be "customer" or "admin".');
        }
    }

    protected function buildClient(string $group, string $redirectUri): Azure
    {
        if ($group === 'customer') {
            if (! $this->isEnabledForCustomer()) {
                throw new OAuthProviderException('Microsoft OAuth is not enabled for customer.');
            }

            return $this->createAzureClient(
                (string) $this->customerClientId,
                (string) $this->customerClientSecret,
                (string) $this->customerTenant,
                $redirectUri,
            );
        }

        if (! $this->isEnabledForAdmin()) {
            throw new OAuthProviderException('Microsoft OAuth is not enabled for admin.');
        }

        return $this->createAzureClient(
            (string) $this->adminClientId,
            (string) $this->adminClientSecret,
            (string) $this->adminTenant,
            $redirectUri,
        );
    }

    protected function createAzureClient(string $clientId, string $clientSecret, string $tenant, string $redirectUri): Azure
    {
        $client = new Azure([
            'clientId' => $clientId,
            'clientSecret' => $clientSecret,
            'redirectUri' => $redirectUri,
            'tenant' => $tenant,
            'defaultEndPointVersion' => Azure::ENDPOINT_VERSION_2_0,
        ]);

        // The Azure provider defaults to v1.0 endpoints unless explicitly overridden;
        // re-assert the v2.0 endpoints so authorize / token URLs use /v2.0/* paths.
        $client->defaultEndPointVersion = Azure::ENDPOINT_VERSION_2_0;

        return $client;
    }
}
