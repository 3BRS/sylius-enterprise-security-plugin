# ThreeBRS Enterprise Security Bundle

A standalone Symfony bundle providing reusable security primitives — two-factor authentication, passkeys (WebAuthn/FIDO2), magic-link login, OAuth (Google + Apple), account lockout with rate limiting, session tracking, IP whitelist, password policy / history / expiration, GDPR self-service account deletion, and a runtime-configurable settings store.

The bundle is framework-agnostic (any Symfony 6.4 / 7.4 app) and is the engine powering the Sylius-flavored [ThreeBRS Enterprise Security Plugin](../../README.md). This document describes how to wire it into a **non-Sylius** Symfony project.

---

## What the bundle ships

**Authentication flows** (abstract base controllers — you extend and bind to your app):
- Passkey login + registration (WebAuthn ceremony, browser-side `navigator.credentials.*`)
- Magic-link login request + verify
- OAuth login + callback + confirm-link (Google, Apple)
- Two-factor authentication setup wizard + recovery challenge

**Self-service actions** (abstract base controllers):
- Session list / revoke / revoke others
- Passkey list / delete
- Two-factor disable / regenerate recovery codes
- 3rd-party OAuth account unlink
- Account deletion request (with grace period)

**Admin actions** (abstract base controllers):
- Unlock user (after lockout)
- Cancel pending account deletion
- Locked users list

**Pure services** (use directly via DI):
- `TotpSecretGeneratorInterface`, `QrCodeGeneratorInterface`, `RecoveryCodeGeneratorInterface`
- `MagicLinkTokenGeneratorInterface`, `MagicLinkTokenValidatorInterface`
- `PasskeyValidatorFactoryInterface`, `PasskeyCeremonyStepManagerFactoryInterface`, `PasskeyRelyingPartyEntityFactoryInterface`, `PasskeyWebauthnSerializerInterface`, `SessionPasskeyOptionsStorageInterface`
- `OAuthProviderRegistryInterface`, `OAuthProviderInterface` + Google + Apple impls
- `AutoRegistrationPolicyInterface`
- `CidrMatcherInterface`, `CidrListValidator` (Symfony constraint)
- `GracePeriodCalculatorInterface`
- `UserAgentParserInterface`, `SessionFingerprintGeneratorInterface`, `GeoIpLookupInterface`
- `RateLimitGuardInterface`, `DynamicRateLimiterFactoryInterface`
- `LockoutPolicyInterface`, `FeatureToggleInterface`, `PolicyFactoryInterface`
- `SettingsProviderInterface`, `SettingsWriterInterface` — runtime-configurable settings store contracts
- `TimingPaddingInterface` (impl: `DeadlineTimingPadding`) — constant-time response padding against enumeration

**Persisted-record contracts** (interfaces — your Doctrine entities implement them):
- `MagicLinkRecordInterface`, `SessionRecordInterface`, `SocialAccountLinkRecordInterface`, `CustomerDeletionRequestRecordInterface`, `PasskeyCredentialRecordInterface`

**Repository contracts** (interfaces — your Doctrine repositories implement them):
- `LockedUserRepositoryInterface`, `CustomerDeletionRequestRepositoryInterface`, `PasskeyCredentialRepositoryInterface`

**Anonymization contract** (interface — your app implements when wiring GDPR self-service deletion):
- `UserAnonymizerInterface`

**User-mixin contracts** (add to your `User` entity, per feature):
- `TwoFactorAuthShopUserInterface` / `TwoFactorAuthAdminUserInterface`
- `LockableShopUserInterface` / `LockableAdminUserInterface`
- `PasswordExpirationShopUserInterface` / `PasswordExpirationAdminUserInterface`

> **About the `Shop` / `Admin` naming:** the two flavors are identical contracts — historic naming from Sylius (which has two firewalls: shop = customers, admin = staff). If your app has **one** firewall, pick either flavor and stick with it. If you have **two** firewalls and want them feature-flagged independently, use both. Bundle abstract controllers work with any firewall via `getFirewallName()`.

---

## Requirements

- PHP 8.3+
- Symfony 6.4 or 7.4
- A Doctrine ORM (the bundle itself has no ORM dep, but your app will need one to persist sessions, passkey credentials, magic link tokens, etc.)
- A user entity implementing `Symfony\Component\Security\Core\User\UserInterface`

---

## Installation

```bash
composer require 3brs/enterprise-security-bundle
```

Then register the bundle in `config/bundles.php`:

```php
return [
    // ... your existing bundles
    Scheb\TwoFactorBundle\SchebTwoFactorBundle::class => ['all' => true],
    ThreeBRS\EnterpriseSecurityBundle\ThreeBRSEnterpriseSecurityBundle::class => ['all' => true],
];
```

The bundle requires `scheb/2fa-bundle` to be present for the 2FA flows. `composer require` will pull it in automatically.

---

## Configuration

### 1. Rate-limiter cache pool (auto-configured)

The bundle pre-configures a dedicated cache pool `three_brs.rate_limiter.cache_pool` (backed by `cache.app`) and the `three_brs.rate_limiter.storage` service. **No action required** for the default setup.

If you need a non-default backend (Redis / Memcached for clustered deployments), override the pool in your `config/packages/framework.yaml`:

```yaml
framework:
    cache:
        pools:
            three_brs.rate_limiter.cache_pool:
                adapter: cache.adapter.redis
                provider: '%env(REDIS_DSN)%'
```

### 2. Settings store

The bundle's settings infrastructure (feature toggles, policies) reads from a `SettingsProviderInterface` and writes through a `SettingsWriterInterface`. You provide the concrete implementations (typically Doctrine-backed). The bundle ships interfaces only:

```php
namespace App\Security\Settings;

use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsProviderInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;

class DbSettingsProvider implements SettingsProviderInterface
{
    public function __construct(private DbConnection $db) {}

    public function get(string $path, SettingsScope $scope): mixed { /* ... */ }
    public function getBool(string $path, SettingsScope $scope): bool { /* ... */ }
    public function getInt(string $path, SettingsScope $scope): int { /* ... */ }
    public function getNullableInt(string $path, SettingsScope $scope): ?int { /* ... */ }
    public function getString(string $path, SettingsScope $scope): string { /* ... */ }
    public function refresh(): void { /* invalidate any in-memory cache */ }
}
```

`SettingsScope` is an enum with three cases: `CUSTOMER`, `ADMIN`, `GLOBAL`. The same setting key can have different values per scope (e.g. customer lockout threshold = 5 attempts, admin = 3).

Then alias the bundle interface to your impl:

```yaml
services:
    App\Security\Settings\DbSettingsProvider: ~

    ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsProviderInterface:
        alias: App\Security\Settings\DbSettingsProvider
```

(The Sylius plugin ships `SettingsProvider` / `SettingsWriter` impls — refer to them for a complete example.)

### 3. Feature flags (compile-time defaults)

Define your default settings via the bundle's `YamlConfigDefaultsProvider`:

```yaml
services:
    ThreeBRS\EnterpriseSecurityBundle\Settings\Defaults\YamlConfigDefaultsProvider:
        arguments:
            $defaults: '%three_brs.security_settings.defaults%'
```

Set the `three_brs.security_settings.defaults` parameter in your kernel extension or `services.yaml` based on your initial configuration. (The Sylius plugin builds this from its Configuration tree.)

### 4. Required scalar parameters

The bundle's `services.yaml` reads a handful of scalar parameters directly. Define them in your `services.yaml` `parameters:` block:

```yaml
parameters:
    # Passkey — relying-party identity (bound to credentials at registration; do not change at runtime)
    three_brs.passkey.rp_id: 'example.com'
    three_brs.passkey.rp_name: 'Example App'

    # OAuth — deployment-time secrets
    three_brs.oauth.customer.google.client_id: '%env(GOOGLE_CLIENT_ID)%'
    three_brs.oauth.customer.google.client_secret: '%env(GOOGLE_CLIENT_SECRET)%'
    # … plus Apple if used; admin variants if you have a separate admin firewall
```

Two more parameters are not read by the bundle directly but typically belong in the same block because your wiring depends on them:

- `three_brs.passkey.skip_2fa_when_user_verified` — passed through the **subclass** constructor of `AbstractPasskeyLoginVerifyController` (see "Wiring up controllers" below). You define the parameter and reference it as `'%three_brs.passkey.skip_2fa_when_user_verified%'` in the controller's service definition.
- `three_brs.two_factor.issuer` — used to configure **`scheb/2fa-bundle`** (the bundle's 2FA dependency), e.g. via `prepend()` in your extension when wiring `scheb_two_factor.totp.issuer`. The bundle controllers themselves do not read it.

---

## User entity setup

Your user entity must implement the appropriate bundle contract interfaces depending on which features you enable.

```php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use ThreeBRS\EnterpriseSecurityBundle\Lockout\LockableShopUserInterface;
use ThreeBRS\EnterpriseSecurityBundle\PasswordExpiration\PasswordExpirationShopUserInterface;
use ThreeBRS\EnterpriseSecurityBundle\TwoFactor\TwoFactorAuthShopUserInterface;

#[ORM\Entity]
class User implements
    UserInterface,
    PasswordAuthenticatedUserInterface,
    TwoFactorAuthShopUserInterface,
    LockableShopUserInterface,
    PasswordExpirationShopUserInterface
{
    // ... your standard user fields (id, email, password, roles)

    // Two-factor (from TwoFactorAuthShopUserInterface)
    #[ORM\Column(nullable: true)]
    protected ?string $totpSecret = null;

    #[ORM\Column(type: 'boolean')]
    protected bool $twoFactorEnabled = false;

    #[ORM\Column(type: 'integer')]
    protected int $trustedTokenVersion = 0;

    public function getTotpSecret(): ?string { return $this->totpSecret; }
    public function setTotpSecret(?string $s): void { $this->totpSecret = $s; }
    public function isTwoFactorEnabled(): bool { return $this->twoFactorEnabled; }
    public function setTwoFactorEnabled(bool $v): void { $this->twoFactorEnabled = $v; }
    public function getTrustedTokenVersion(): int { return $this->trustedTokenVersion; }
    public function bumpTrustedTokenVersion(): void { ++$this->trustedTokenVersion; }

    // Lockout (from LockableShopUserInterface)
    #[ORM\Column(type: 'integer')]
    protected int $failedLoginAttempts = 0;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    protected ?\DateTimeImmutable $lockedUntil = null;

    // … getters/setters per the interface
}
```

The bundle ships traits in the Sylius plugin (see [plugin source](../../src/Model/)) you can copy as a starting point if your schema is similar.

---

## Persisted entities you must provide

The bundle defines **record contracts** for some entities; for others it just consumes data your repository returns. Either way, you persist these in your ORM and create migrations for them.

| Entity | Bundle contract | Fields (typical) |
|---|---|---|
| `UserMagicLinkToken` | implements `MagicLinkRecordInterface` | id, user FK, tokenHash, expiresAt, usedAt, createdAt |
| `UserSession` | implements `SessionRecordInterface` | id, user FK, sessionId, userAgent, ipAddress, country, city, createdAt, lastActivityAt, revokedAt |
| `UserSocialAccountLink` | implements `SocialAccountLinkRecordInterface` | id, user FK, provider, providerUserId, email, linkedAt, lastUsedAt |
| `UserDeletionRequest` | implements `CustomerDeletionRequestRecordInterface` | id, user FK, requestedAt, scheduledFor, cancelledAt, requestedByAdmin |
| `UserPasskeyCredential` | implements `PasskeyCredentialRecordInterface` | id, user FK, credentialId, credentialSource (array), label, createdAt, lastUsedAt |
| `UserRecoveryCode` | *(your shape — hash via bundle's `RecoveryCodeGeneratorInterface`)* | id, user FK, codeHash, consumedAt |
| `UserPasswordHistory` | *(optional — only if you wire `PasswordHistory` constraint into your password-change form)* | id, user FK, passwordHash, createdAt |

Write thin Doctrine repositories with the lookup methods your controllers will need (`findActiveForUser`, `findOneByTokenHash`, etc.). Generate migrations the usual way (`bin/console doctrine:migrations:diff`).

---

## Required interface implementations

The bundle ships **contracts**, not implementations. For each enabled feature, you provide a concrete class and alias the bundle interface to it.

| Bundle interface | Required when | Typical impl |
|---|---|---|
| `SettingsProviderInterface` | Always (used by feature toggles, policies, all controllers' `$enabled` flag) | Doctrine repository wrapping a `SecuritySetting` entity |
| `SettingsWriterInterface` | Only if you have an admin UI to mutate settings at runtime | Doctrine `EntityManager` with optimistic locking |
| `MagicLinkTokenVerifierInterface` | If magic-link login enabled | Repository lookup by `tokenHash` + expiry/used check |
| `PasskeyAssertionVerifierInterface` | If passkey login enabled | Repository lookup by `credentialId` + WebAuthn verify via bundle's `PasskeyValidatorFactory` |
| `OAuthProviderInterface` *(× N)* | Only if you add providers beyond Google/Apple (bundle ships those two) | Provider-specific OAuth2 client wrapper, tag with `three_brs.oauth_provider` |

### Reference impl: Settings provider (Doctrine-backed)

```php
namespace App\Security\Settings;

use App\Entity\SecuritySetting;
use App\Repository\SecuritySettingRepository;
use ThreeBRS\EnterpriseSecurityBundle\Settings\Defaults\SettingsDefaultsProviderInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsProviderInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;

class DbSettingsProvider implements SettingsProviderInterface
{
    /** @var array<string, mixed>|null */
    protected ?array $cache = null;

    public function __construct(
        protected SecuritySettingRepository $repository,
        protected SettingsDefaultsProviderInterface $defaults,
    ) {
    }

    public function getBool(string $path, SettingsScope $scope): bool
    {
        return (bool) $this->resolve($path, $scope);
    }

    public function getInt(string $path, SettingsScope $scope): int
    {
        return (int) $this->resolve($path, $scope);
    }

    public function getNullableInt(string $path, SettingsScope $scope): ?int
    {
        $v = $this->resolve($path, $scope);
        return $v === null ? null : (int) $v;
    }

    public function getString(string $path, SettingsScope $scope): string
    {
        return (string) $this->resolve($path, $scope);
    }

    public function get(string $path, SettingsScope $scope): mixed
    {
        return $this->resolve($path, $scope);
    }

    public function refresh(): void
    {
        $this->cache = null;
    }

    protected function resolve(string $path, SettingsScope $scope): mixed
    {
        $key = $scope->value . '.' . $path;
        if ($this->cache === null) {
            $this->cache = [];
            foreach ($this->repository->findAll() as $row) {
                $this->cache[$row->getScope() . '.' . $row->getPath()] = $row->getValue();
            }
        }
        return $this->cache[$key] ?? $this->defaults->getDefaultFor($path, $scope);
    }
}
```

Then alias the bundle interface:

```yaml
services:
    App\Security\Settings\DbSettingsProvider: ~

    ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsProviderInterface:
        alias: App\Security\Settings\DbSettingsProvider
```

### Reference impl: Magic-link verifier

```php
namespace App\Security\MagicLink;

use App\Repository\UserMagicLinkTokenRepository;
use Psr\Clock\ClockInterface;
use ThreeBRS\EnterpriseSecurityBundle\MagicLink\MagicLinkRecordInterface;
use ThreeBRS\EnterpriseSecurityBundle\MagicLink\MagicLinkTokenGeneratorInterface;
use ThreeBRS\EnterpriseSecurityBundle\MagicLink\MagicLinkTokenValidatorInterface;
use ThreeBRS\EnterpriseSecurityBundle\MagicLink\MagicLinkTokenVerifierInterface;

class MagicLinkTokenVerifier implements MagicLinkTokenVerifierInterface
{
    public function __construct(
        protected UserMagicLinkTokenRepository $repository,
        protected MagicLinkTokenGeneratorInterface $generator,
        protected MagicLinkTokenValidatorInterface $validator,
    ) {
    }

    public function verify(string $plainToken): ?MagicLinkRecordInterface
    {
        $hash = $this->generator->hash($plainToken);
        $record = $this->repository->findOneByTokenHash($hash);
        if ($record === null) {
            return null;
        }
        return $this->validator->isUsable($record) ? $record : null;
    }
}
```

Bundle's `MagicLinkTokenGenerator` + `MagicLinkTokenValidator` are concrete — you reuse them. You only write the **repository lookup** glue.

### Reference impl: Passkey assertion verifier

Heavier example because WebAuthn ceremony involves multiple bundle services. See [the Sylius plugin's `CustomerPasskeyAssertionVerifier`](../../src/Service/Passkey/CustomerPasskeyAssertionVerifier.php) for a complete ~80-line example. The skeleton:

```php
class PasskeyAssertionVerifier implements PasskeyAssertionVerifierInterface
{
    public function __construct(
        protected PasskeyValidatorFactoryInterface $validatorFactory,   // bundle
        protected PasskeyWebauthnSerializerInterface $serializer,        // bundle
        protected SessionPasskeyOptionsStorageInterface $sessionStorage, // bundle
        protected UserPasskeyCredentialRepository $repo,                 // your repo
        protected EntityManagerInterface $em,                            // your EM
        protected ClockInterface $clock,
    ) {}

    public function verify(string $credentialResponseJson, string $host): PasskeyAssertionResultInterface
    {
        // 1. Read pending options from session (set during /passkey/login/options)
        $optionsJson = $this->sessionStorage->retrieve('shop.assertion_options')
            ?? throw new \RuntimeException('No pending passkey ceremony.');

        // 2. Deserialize options + the user's response
        $options = $this->serializer->deserializeRequestOptions($optionsJson);
        $response = $this->serializer->deserializeCredential($credentialResponseJson);

        // 3. Look up the credential by ID; verify via bundle's WebAuthn validator
        $credential = $this->repo->findOneByCredentialId($response->id)
            ?? throw new \RuntimeException('Unknown credential.');

        $validator = $this->validatorFactory->build($host);
        $validatedCredential = $validator->check($response, $options, $credential->toSource(), $host);

        // 4. Update signCount + lastUsedAt, return result
        $credential->setSignCount($validatedCredential->counter);
        $credential->setLastUsedAt($this->clock->now());
        $this->em->flush();

        return new PasskeyAssertionResult($credential->getUser(), $validatedCredential->isUserVerified);
    }
}
```

`PasskeyAssertionResult` is a small DTO you write (or copy from the plugin) implementing `PasskeyAssertionResultInterface`.

---

## Wiring up controllers

This is the bundle's main extension surface. Each abstract base controller defines a security flow; you extend it with bindings for your app (user type, routes, templates, repositories).

### Example: passkey login verify (the WebAuthn assertion endpoint)

**Step 1.** Implement the verifier (Doctrine lookup + WebAuthn validation):

```php
namespace App\Security\Passkey;

use App\Entity\User;
use App\Repository\PasskeyCredentialRepository;
use ThreeBRS\EnterpriseSecurityBundle\Passkey\PasskeyAssertionVerifierInterface;
use ThreeBRS\EnterpriseSecurityBundle\Passkey\PasskeyAssertionResultInterface;
use ThreeBRS\EnterpriseSecurityBundle\Passkey\PasskeyValidatorFactoryInterface;
use ThreeBRS\EnterpriseSecurityBundle\Passkey\PasskeyWebauthnSerializerInterface;
use ThreeBRS\EnterpriseSecurityBundle\Passkey\SessionPasskeyOptionsStorageInterface;

class PasskeyAssertionVerifier implements PasskeyAssertionVerifierInterface
{
    public function __construct(
        protected PasskeyValidatorFactoryInterface $validatorFactory,
        protected PasskeyWebauthnSerializerInterface $serializer,
        protected SessionPasskeyOptionsStorageInterface $sessionStorage,
        protected PasskeyCredentialRepository $repo,
    ) {}

    public function verify(string $credentialResponseJson, string $host): PasskeyAssertionResultInterface
    {
        // 1. read pending options from session
        // 2. deserialize response, look up credential by ID via $this->repo
        // 3. validate via $this->validatorFactory->build(...)
        // 4. update signCount, return result
    }
}
```

**Step 2.** Extend the abstract base controller:

```php
namespace App\Controller;

use App\Entity\User;
use App\Security\Passkey\PasskeyAssertionVerifier;
use App\Security\Session\PostLoginSessionTracker;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Scheb\TwoFactorBundle\Security\Http\Authentication\AuthenticationRequiredHandlerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use ThreeBRS\EnterpriseSecurityBundle\Controller\AbstractPasskeyLoginVerifyController;

class PasskeyLoginVerifyController extends AbstractPasskeyLoginVerifyController
{
    public function __construct(
        PasskeyAssertionVerifier $verifier,
        TokenStorageInterface $tokenStorage,
        EventDispatcherInterface $eventDispatcher,
        AuthenticationRequiredHandlerInterface $twoFactorHandler,
        RouterInterface $router,
        LoggerInterface $logger,
        protected PostLoginSessionTracker $sessionTracker,
        bool $enabled,
        bool $skipTwoFactorWhenUserVerified,
    ) {
        parent::__construct(
            $verifier, $tokenStorage, $eventDispatcher, $twoFactorHandler,
            $router, $logger, $enabled, $skipTwoFactorWhenUserVerified,
        );
    }

    protected function getFirewallName(): string
    {
        return 'main';                   // your firewall name
    }

    protected function getDefaultRedirectUrl(): string
    {
        return $this->router->generate('app_dashboard');
    }

    protected function getLogChannel(): string
    {
        return 'app.passkey';
    }

    protected function handlePostLogin(UserInterface $user, Request $request): void
    {
        // optional: track session, send "new device" email, etc.
        if ($user instanceof User) {
            $this->sessionTracker->onLogin($user, $request);
        }
    }
}
```

**Step 3.** Register it as a service:

```yaml
services:
    App\Controller\PasskeyLoginVerifyController:
        arguments:
            $verifier: '@App\Security\Passkey\PasskeyAssertionVerifier'
            $tokenStorage: '@security.token_storage'
            $eventDispatcher: '@security.event_dispatcher.main'
            $twoFactorHandler: '@security.authentication.authentication_required_handler.two_factor.main'
            $router: '@router'
            $logger: '@logger'
            $sessionTracker: '@App\Security\Session\PostLoginSessionTracker'
            $enabled: true
            $skipTwoFactorWhenUserVerified: true
        tags:
            - { name: 'controller.service_arguments' }
```

**Step 4.** Add the route:

```yaml
# config/routes.yaml
app_passkey_login_verify:
    path: /passkey/login/verify
    controller: App\Controller\PasskeyLoginVerifyController
    methods: [POST]
```

Repeat for every flow you want enabled (typically ~15–25 controllers across all features). All abstract controllers follow the same pattern: a constructor passing shared deps to the parent + a small set of abstract methods to implement.

### Multi-firewall apps

If your app has **separate firewalls** (e.g. `shop` for customers, `admin` for staff), write **two** subclasses per feature — one per firewall — each returning its own `getFirewallName()`, route names, and templates. Register two service instances. The Sylius plugin does this throughout `src/Controller/Shop/` and `src/Controller/Admin/`.

### Example: registering a concrete list controller

The list / overview controllers (`LockedUsersListController`, `AccountDeletionsListController`, `SocialAccountsOverviewController`) need no subclass — register them directly with the appropriate DI arguments and tag as a controller:

```yaml
services:
    app.controller.admin.locked_users:
        class: ThreeBRS\EnterpriseSecurityBundle\Controller\LockedUsersListController
        arguments:
            $repository: '@App\Repository\LockedUserRepository'
            $twig: '@twig'
            $template: 'admin/locked_users.html.twig'
            $enabled: '%app.lockout.enabled%'
        tags:
            - { name: 'controller.service_arguments' }

    app.controller.admin.account_deletions:
        class: ThreeBRS\EnterpriseSecurityBundle\Controller\AccountDeletionsListController
        arguments:
            $repository: '@App\Repository\CustomerDeletionRequestRepository'
            $twig: '@twig'
            $template: 'admin/pending_deletions.html.twig'
            $enabled: '%app.account_deletion.enabled%'
        tags:
            - { name: 'controller.service_arguments' }

    app.controller.shop.social_accounts:
        class: ThreeBRS\EnterpriseSecurityBundle\Controller\SocialAccountsOverviewController
        arguments:
            $twig: '@twig'
            $template: 'account/social_accounts.html.twig'
        tags:
            - { name: 'controller.service_arguments' }
```

Then reference the service IDs (not class names) in `routes.yaml`:

```yaml
app_admin_locked_users:
    path: /admin/locked-users
    controller: app.controller.admin.locked_users
    methods: [GET]
```

For multi-firewall apps register the same class twice with different repos + templates (one per firewall).

---

## Routes reference

URLs are up to you — these are the controllers and the typical HTTP verbs. Pick paths and route names that fit your app.

| Controller (abstract) | Method | Sample path | Notes |
|---|---|---|---|
| `PasskeyLoginOptionsController` *(concrete)* | POST | `/passkey/login/options` | Returns WebAuthn assertion options JSON |
| `AbstractPasskeyLoginVerifyController` | POST | `/passkey/login/verify` | Submits credential response, completes login |
| `AbstractPasskeyRegistrationOptionsController` | POST | `/passkey/register/options` | Returns WebAuthn creation options JSON |
| `AbstractPasskeyRegistrationVerifyController` | POST | `/passkey/register/verify` | Submits credential, persists |
| `AbstractPasskeyListController` | GET | `/account/passkey` | Lists user's passkeys |
| `AbstractPasskeyDeleteController` | POST | `/account/passkey/{id}/delete` | CSRF-protected delete |
| `AbstractMagicLinkRequestController` | GET, POST | `/magic-link/request` | Renders form / dispatches email |
| `AbstractMagicLinkVerifyController` | GET | `/magic-link/verify/{token}` | Email click target |
| `AbstractOAuthInitiateController` | GET | `/oauth/{provider}` | Redirects to provider |
| `AbstractOAuthCallbackController` | GET | `/oauth/{provider}/callback` | Provider callback target |
| `AbstractOAuthConfirmLinkController` | GET, POST | `/oauth/confirm-link` | Existing user password verify |
| `AbstractSocialAccountUnlinkController` | POST | `/account/social/{provider}/unlink` | CSRF-protected unlink |
| `AbstractTwoFactorSetupController` | GET, POST | `/account/two-factor` | Setup wizard / manage |
| `AbstractTwoFactorRecoveryChallengeController` | GET, POST | `/2fa/recovery` | Login completion via recovery code |
| `AbstractTwoFactorDisableController` | POST | `/account/two-factor/disable` | CSRF-protected disable |
| `AbstractTwoFactorRegenerateRecoveryCodesController` | POST | `/account/two-factor/regenerate` | CSRF-protected regenerate |
| `AbstractSessionsListController` | GET | `/account/sessions` | List active sessions |
| `AbstractSessionRevokeController` | POST | `/account/sessions/{id}/revoke` | CSRF-protected |
| `AbstractSessionRevokeOthersController` | POST | `/account/sessions/revoke-others` | CSRF-protected |
| `LockedUsersListController` *(concrete)* | GET | `/admin/locked-users` | Admin list |
| `AbstractUnlockUserController` | POST | `/admin/locked-users/{id}/unlock` | Admin CSRF-protected |
| `AbstractAccountDeletionRequestController` | GET, POST | `/account/delete` | Customer request form |
| `AccountDeletionsListController` *(concrete)* | GET | `/admin/deletions` | Admin list of pending deletions |
| `AbstractAccountDeletionCancelController` | POST | `/admin/deletions/{id}/cancel` | Admin CSRF-protected |
| `SocialAccountsOverviewController` *(concrete)* | GET | `/account/social-accounts` | Render-only overview of linked accounts |

---

## Symfony security configuration

The bundle does not auto-configure your firewalls — you do. Minimum setup for the supported flows:

### Passkey + magic-link login

These flows write the authenticated token manually via the abstract controllers (after the WebAuthn / magic-link verification). You only need a standard firewall that recognises the token; **no custom authenticator** required:

```yaml
# config/packages/security.yaml
security:
    firewalls:
        main:
            lazy: true
            provider: app_user_provider
            # ... your existing form_login, json_login, etc.
```

### Two-factor authentication

Install `scheb/2fa-bundle` (auto-pulled by this bundle) and configure it per its [docs](https://github.com/scheb/2fa). Minimum:

```yaml
# config/packages/scheb_2fa.yaml
scheb_two_factor:
    security_tokens:
        - Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken
    totp:
        enabled: true
        issuer: '%env(APP_NAME)%'

security:
    firewalls:
        main:
            two_factor:
                auth_form_path: app_2fa_login_form
                check_path: app_2fa_login_check
                prepare_on_login: true
                prepare_on_access_denied: true
```

Make sure your `User` entity also implements scheb's `TwoFactorInterface` from `scheb/2fa-totp` (the bundle's `TwoFactorAuthShopUserInterface` / `TwoFactorAuthAdminUserInterface` define the storage methods; scheb's interface defines the verification hook).

### OAuth

OAuth itself doesn't need security.yaml changes — the bundle's `AbstractOAuthCallbackController` handles the entire flow and manually sets the security token. Just register your `App\OAuth\Google\GoogleProvider` and `App\OAuth\Apple\AppleProvider` services with the `three_brs.oauth_provider` tag (the bundle's registry picks them up).

---

## Front-end JavaScript for WebAuthn

The bundle is server-only. Passkey flows need browser-side calls to `navigator.credentials.create()` (registration) and `navigator.credentials.get()` (login). You can:

1. **Copy the JS** from the [Sylius plugin's `src/Resources/public/js/passkey.js`](../../src/Resources/public/js/passkey.js) — it talks to the bundle's options/verify endpoints out of the box.
2. **Write your own** using `@simplewebauthn/browser` or vanilla `navigator.credentials.*`. The bundle's `PasskeyWebauthnSerializer` produces JSON the browser API consumes directly.

The browser flow is:

```
POST  /passkey/login/options       → JSON options
navigator.credentials.get(options) → credential
POST  /passkey/login/verify        → { ok: true, redirect: '/dashboard' }
```

Same shape for registration (`create` instead of `get`, register-options + register-verify endpoints).

---

## Reference: abstract controllers shipped

Each lives in `ThreeBRS\EnterpriseSecurityBundle\Controller\`. Number after the name = count of abstract methods you implement.

**Authentication flows:**
- `AbstractPasskeyLoginVerifyController` (4) — verify WebAuthn assertion, authenticate, redirect (2FA-aware)
- `AbstractPasskeyRegistrationOptionsController` (2) — return WebAuthn creation options JSON
- `AbstractPasskeyRegistrationVerifyController` (3) — verify + persist credential
- `AbstractPasskeyDeleteController` (6) — CSRF + look-up + last-method guard + delete
- `AbstractPasskeyListController` (3) — fetch + render
- `AbstractMagicLinkRequestController` (4) — form + dispatch
- `AbstractMagicLinkVerifyController` (8) — verify token + authenticate (2FA-aware)
- `AbstractOAuthInitiateController` (5) — state CSRF + provider redirect
- `AbstractOAuthCallbackController` (20) — fetch user info + login or link branching
- `AbstractOAuthConfirmLinkController` (12) — password verify + link existing user
- `AbstractTwoFactorSetupController` (13) — TOTP + QR + recovery-code wizard. After verification, writes plaintext recovery codes to session under the key returned by `getPlainRecoveryCodesSessionKey()` and redirects to `getRecoveryCodesDisplayUrl()` — that URL **must** point to a one-shot display controller you provide (see "Other controllers your app must provide" §5 below).
- `AbstractTwoFactorRecoveryChallengeController` (5) — recovery-code login completion
- `AbstractTwoFactorDisableController` (5) — disable + invalidate codes + rotate trusted-token
- `AbstractTwoFactorRegenerateRecoveryCodesController` (7) — generate + replace. Same session-handoff pattern as setup (writes to `getPlainRecoveryCodesSessionKey()`, redirects to `getRecoveryCodesDisplayUrl()`); the redirect target is the same one-shot display controller you wrote for setup.

**Self-service / admin actions:**
- `AbstractSessionRevokeController` (4)
- `AbstractSessionRevokeOthersController` (3)
- `AbstractSessionsListController` (3)
- `AbstractSocialAccountUnlinkController` (7) — CSRF + last-method guard + delete + audit
- `AbstractUnlockUserController` (3) — admin: CSRF + lockoutManager.unlock
- `AbstractAccountDeletionCancelController` (2) — admin: cancel pending deletion
- `AbstractAccountDeletionRequestController` (7) — customer: password verify + grace period

**Concrete (no extension needed — register one or more instances per firewall with the appropriate DI arguments):**
- `PasskeyLoginOptionsController` — pure JSON API; inject `PasskeyAssertionOptionsBuilderInterface` impl + `PasskeyWebauthnSerializerInterface` + `bool $enabled` (throws 404 when disabled)
- `LockedUsersListController` — render-only list; inject `LockedUserRepositoryInterface` impl + `Twig\Environment` + `string $template` + `bool $enabled` (throws 404 when disabled)
- `AccountDeletionsListController` — render-only list of pending deletion requests; inject `CustomerDeletionRequestRepositoryInterface` impl + `Twig\Environment` + `string $template` + `bool $enabled` (throws 404 when disabled)
- `SocialAccountsOverviewController` — render-only overview of the current user's linked social accounts; inject `Twig\Environment` + `string $template` (logic lives in the template, iterating `user.socialAccounts`)

---

## Other controllers your app must provide

The bundle ships **flow controllers** (login ceremonies, CSRF-protected actions) — but several pieces of a complete security UI are intentionally not abstracted because they are framework- or admin-UI-specific. You write them. The Sylius plugin has reference implementations of all of these under `src/Controller/`.

### 1. Settings admin UI

If you wire `SettingsWriterInterface` for runtime-mutable settings, you also need an admin page that renders the form, validates input, and persists. Any form solution works — the bundle only requires submitted values to reach `SettingsWriterInterface::write(...)`.

Sylius plugin reference: `src/Controller/Admin/SecuritySettings/{IndexController,SaveTabController}.php` (compound Symfony form wrapping per-tab subforms).

### 2. Admin actions targeting another user

When an admin acts on a specific user (block their account, force a password reset on next login, kill all their active sessions, kill one specific session), you write small POST handlers — each does CSRF check + repository lookup + one mutation + flash + redirect back to the user detail page. The bundle has no abstracts because admin URL structures and detail-page route names vary too much per framework.

Sylius plugin reference (5 controllers + shared base): `src/Controller/Admin/Customer/{BlockAccount,UnblockAccount,ForcePasswordReset,RevokeAllSessions,RevokeSession}Controller.php`. The shared `AbstractCustomerSecurityActionController` handles CSRF + lookup + flash; concrete controllers only fill in the mutation.

### 3. Force-password-change UI

`PasswordExpirationChecker` flags users whose password has expired or who have `forcePasswordChange = true` on their user entity. Your app needs:

- An **event listener** (`kernel.request`) that redirects flagged users to a change-password page from anywhere they navigate to.
- The **change-password page itself**: form + handler that hashes the new password, clears the flag, invalidates the session, redirects to login.

Without this UI, the expiration flag never leads anywhere — the checker only knows the user *should* change their password, not how to make them.

Sylius plugin reference: `src/Controller/Admin/ForcePasswordChangeController.php` (~65 lines).

### 4. IP whitelist admin UI

`CidrMatcherInterface` and the `CidrList` Symfony constraint are the building blocks; the admin UI is yours. Typical shape:

- A **list page** showing admins × their per-user whitelist (enabled flag + CIDR list).
- An **edit page** with a form (enabled toggle + CIDR list, validated by the bundle's `CidrList` constraint) that persists to your `AdminUserIpWhitelist` entity.

Sylius plugin reference: `src/Controller/Admin/IpWhitelistAdminsController.php` (list) and `src/Controller/Admin/IpWhitelistAdminEditController.php` (edit).

### 5. Recovery-codes one-shot display page **(critical)**

When `AbstractTwoFactorSetupController` or `AbstractTwoFactorRegenerateRecoveryCodesController` succeed, they write the **plaintext recovery codes** to session (under the key returned from `getPlainRecoveryCodesSessionKey()`) and redirect to the URL returned from `getRecoveryCodesDisplayUrl()`. That redirect target **must** be a controller you write that:

1. reads codes from session under the same key,
2. removes them from session (one-shot, never displayed again),
3. renders them so the user can write them down.

**If you forget this controller, the user never sees their recovery codes** — the codes exist only in session and the user is sent to a URL that does nothing with them. Both setup and regenerate flows depend on this display controller.

Sylius plugin reference: `src/Controller/{Admin,Shop}/TwoFactorRecoveryCodesController.php` (~44 lines each).

Skeleton:

```php
class TwoFactorRecoveryCodesController
{
    public const SESSION_KEY = 'app_plain_recovery_codes';

    public function __construct(
        protected TokenStorageInterface $tokenStorage,
        protected RouterInterface $router,
        protected Environment $twig,
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof UserInterface) {
            return new RedirectResponse($this->router->generate('app_login'));
        }

        $session = $request->getSession();
        $codes = $session->get(self::SESSION_KEY);
        if (!is_array($codes) || $codes === []) {
            return new RedirectResponse($this->router->generate('app_dashboard'));
        }

        $session->remove(self::SESSION_KEY);

        return new Response($this->twig->render('account/two_factor/recovery_codes.html.twig', ['codes' => $codes]));
    }
}
```

Your setup + regenerate subclasses then return `TwoFactorRecoveryCodesController::SESSION_KEY` from `getPlainRecoveryCodesSessionKey()`, and `$this->router->generate('app_two_factor_recovery_codes')` from `getRecoveryCodesDisplayUrl()`.

---

## Templates

The bundle does not ship Twig templates — your app provides its own. Every abstract list / form controller has a `getTemplate(): string` method you return your template path from.

Templates the bundle controllers will render (you write them):

| Feature | Template variables passed |
|---|---|
| Sessions list | `rows: list<{session, userAgent, isCurrent}>` |
| Passkey list | `credentials: list<PasskeyCredentialInterface>` |
| Locked users list | `users: iterable<User>` |
| 2FA setup form | `form, qr_data_uri, secret` |
| 2FA manage page | `disable_csrf_token, regenerate_csrf_token, recovery_codes_enabled` |
| 2FA recovery challenge | `error: ?string` |
| Magic link request form | `form` |
| OAuth confirm link | `email, provider, error: ?string` |
| Account deletion request | `form` |

JSON API controllers (`PasskeyLoginOptions`, `PasskeyRegistrationOptions`, `PasskeyLoginVerify`, `PasskeyRegistrationVerify`) return JSON — no templates needed.

---

## Translation domains

Flash messages and validation errors use these Symfony translation domains:

- **flashes**: `three_brs.account_deletion.*`, `three_brs.lockout.*`, `three_brs.session.*`, `three_brs.two_factor.disabled`, `three_brs.ui.*`
- **validators**: `three_brs.two_factor.invalid_code`, etc.

Copy the keys from [the plugin's translations](../../src/Resources/translations/) and translate them. Keys with no UI value default to English fallback.

---

## Security checklist when extending controllers

When you write subclasses, verify these contracts (the abstract base does the heavy lifting, but each subclass owns a few bindings):

1. **`isAcceptableUser($user)` must narrow the user type** — return false for users who shouldn't be allowed to hit this endpoint. Returning `true` blindly is an access-control bypass.
2. **`createXxxForm()` must use a CSRF-enabled form type** — Symfony forms have CSRF on by default; do not disable.
3. **`persist + flush` calls must be atomic** — don't split a logical mutation across multiple flushes (e.g. set TOTP secret AND persist recovery codes in one transaction).
4. **Session keys must be unique per firewall** — `getPendingSecretSessionKey()`, `getConfirmPendingSessionKey()`, etc. — if two firewalls share the same key, an admin user could read a customer's pending state.
5. **`getLogChannel()` and `getAuditChannel()` should be unique per firewall** — `app.passkey.shop` vs `app.passkey.admin` so log searches stay sane.

---

## Running tests locally

If you clone the source (to fork, contribute, or debug a problem):

```bash
cd packages/enterprise-security-bundle
composer install
vendor/bin/phpunit              # services + abstract controllers (~280 tests)
vendor/bin/phpstan analyse      # level=max, generics + symfony extensions
vendor/bin/ecs check            # coding standard
```

If you're working inside the parent monorepo, the project Makefile wraps these:

```bash
make bundle-tests               # composer install + PHPStan
make ci                         # plugin + bundle, end-to-end (PHPUnit, Behat, ECS, PHPStan)
```

The bundle is independent of the plugin — `make bundle-tests` runs against a clean install of just the bundle's deps, which is what an external consumer's `composer require 3brs/enterprise-security-bundle` produces.

---

## Reference implementation

The [Sylius Enterprise Security Plugin](../../README.md) is a complete, production-grade integration of this bundle for [Sylius](https://sylius.com). Browse [the plugin's `src/`](../../src/) for end-to-end examples of:
- All bundle controllers extended with Sylius bindings
- Bundle service interfaces implemented over Doctrine
- Sylius admin UI (form types, templates, menu entries) on top of the bundle's primitives

If you build a Symfony-only or Laminas integration on top of this bundle, please open a PR to link it from this README.

---

## License

MIT
