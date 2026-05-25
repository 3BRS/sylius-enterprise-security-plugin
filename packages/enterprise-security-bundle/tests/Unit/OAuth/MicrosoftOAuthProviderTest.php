<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\EnterpriseSecurityBundle\Unit\OAuth;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use ThreeBRS\EnterpriseSecurityBundle\OAuth\Exception\OAuthProviderException;
use ThreeBRS\EnterpriseSecurityBundle\OAuth\MicrosoftOAuthProvider;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsProviderInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;

#[CoversClass(MicrosoftOAuthProvider::class)]
class MicrosoftOAuthProviderTest extends TestCase
{
    public function testGetName(): void
    {
        $provider = $this->provider();

        self::assertSame('microsoft', $provider->getName());
    }

    public function testIsEnabledForCustomerRequiresAllFields(): void
    {
        self::assertTrue($this->provider(customerEnabled: true)->isEnabledForCustomer());
        self::assertFalse($this->provider(customerEnabled: false)->isEnabledForCustomer());
        self::assertFalse($this->provider(customerEnabled: true, customerClientId: null)->isEnabledForCustomer());
        self::assertFalse($this->provider(customerEnabled: true, customerClientId: '')->isEnabledForCustomer());
        self::assertFalse($this->provider(customerEnabled: true, customerClientSecret: null)->isEnabledForCustomer());
        self::assertFalse($this->provider(customerEnabled: true, customerClientSecret: '')->isEnabledForCustomer());
        self::assertFalse($this->provider(customerEnabled: true, customerTenant: null)->isEnabledForCustomer());
        self::assertFalse($this->provider(customerEnabled: true, customerTenant: '')->isEnabledForCustomer());
    }

    public function testIsEnabledForAdminRequiresAllFields(): void
    {
        self::assertTrue($this->provider(adminEnabled: true)->isEnabledForAdmin());
        self::assertFalse($this->provider(adminEnabled: false)->isEnabledForAdmin());
        self::assertFalse($this->provider(adminEnabled: true, adminClientId: null)->isEnabledForAdmin());
        self::assertFalse($this->provider(adminEnabled: true, adminClientId: '')->isEnabledForAdmin());
        self::assertFalse($this->provider(adminEnabled: true, adminClientSecret: null)->isEnabledForAdmin());
        self::assertFalse($this->provider(adminEnabled: true, adminClientSecret: '')->isEnabledForAdmin());
        self::assertFalse($this->provider(adminEnabled: true, adminTenant: null)->isEnabledForAdmin());
        self::assertFalse($this->provider(adminEnabled: true, adminTenant: '')->isEnabledForAdmin());
    }

    // NOTE: We deliberately do NOT unit-test the happy-path of getAuthorizationUrl() for Microsoft.
    // The thenetworg/oauth2-azure SDK fetches OpenID Connect discovery metadata from a live Azure
    // endpoint before constructing the URL, which makes a true unit test (without network or a
    // GUID-shaped fake client_id that still hits Azure) impractical. Our wrapper only delegates
    // — assertGroup() + isEnabledFor*() routing are still covered by the reject/disabled tests
    // below, and the SDK itself owns the URL composition contract.

    public function testGetAuthorizationUrlRejectsUnknownGroup(): void
    {
        $provider = $this->provider(customerEnabled: true);

        $this->expectException(OAuthProviderException::class);
        $provider->getAuthorizationUrl('https://example.com/cb', 'state', 'invalid');
    }

    public function testGetAuthorizationUrlFailsForDisabledGroup(): void
    {
        $provider = $this->provider(customerEnabled: false);

        $this->expectException(OAuthProviderException::class);
        $provider->getAuthorizationUrl('https://example.com/cb', 'state', 'customer');
    }

    public function testFetchUserInfoRejectsInvalidGroup(): void
    {
        $provider = $this->provider(customerEnabled: true);
        $request = new Request([
            'state' => 's',
            'code' => 'c',
        ]);

        $this->expectException(OAuthProviderException::class);
        $provider->fetchUserInfo($request, 'https://example.com/cb', 's', 'invalid');
    }

    public function testFetchUserInfoRejectsMismatchedState(): void
    {
        $provider = $this->provider(customerEnabled: true);
        $request = new Request([
            'state' => 'wrong',
            'code' => 'c',
        ]);

        $this->expectException(OAuthProviderException::class);
        $this->expectExceptionMessage('Invalid OAuth state');
        $provider->fetchUserInfo($request, 'https://example.com/cb', 'expected', 'customer');
    }

    public function testFetchUserInfoRejectsMissingCode(): void
    {
        $provider = $this->provider(customerEnabled: true);
        $request = new Request([
            'state' => 's',
        ]);

        $this->expectException(OAuthProviderException::class);
        $this->expectExceptionMessage('Missing authorization code');
        $provider->fetchUserInfo($request, 'https://example.com/cb', 's', 'customer');
    }

    private function provider(
        bool $customerEnabled = true,
        ?string $customerClientId = 'cid',
        ?string $customerClientSecret = 'sec',
        ?string $customerTenant = 'common',
        bool $adminEnabled = true,
        ?string $adminClientId = 'acid',
        ?string $adminClientSecret = 'asec',
        ?string $adminTenant = 'common',
    ): MicrosoftOAuthProvider {
        $settings = $this->createStub(SettingsProviderInterface::class);
        $settings->method('getBool')->willReturnCallback(
            static fn (string $path, SettingsScope $scope): bool => match ($scope) {
                SettingsScope::CUSTOMER => $customerEnabled,
                SettingsScope::ADMIN => $adminEnabled,
                default => false,
            },
        );

        return new MicrosoftOAuthProvider(
            $settings,
            $customerClientId,
            $customerClientSecret,
            $customerTenant,
            $adminClientId,
            $adminClientSecret,
            $adminTenant,
        );
    }
}
