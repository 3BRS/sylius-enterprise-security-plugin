<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Controller\Admin;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Yaml\Yaml;

/**
 * Authorization for these endpoints comes from the application's access_control,
 * which the plugin does not ship and cannot see. A controller that acts on
 * another account — blocking a customer, unlocking an administrator, writing the
 * security settings — states the role it needs itself, so it stays closed
 * whatever the integrator's security.yaml turns out to say.
 *
 * The list is derived from routing rather than written out, so a new admin route
 * arrives here for a decision instead of quietly shipping without one.
 */
class AdministrationAccessTest extends TestCase
{
    protected const REQUIRED_ROLE = 'ROLE_ADMINISTRATION_ACCESS';

    /**
     * Routes that must stay reachable for someone who is not (yet) a fully
     * authenticated administrator, with the reason each one is on the list.
     */
    protected const NO_ADMIN_IDENTITY_YET = [
        // Sign-in flows: the caller is anonymous by definition.
        'three_brs_admin_magic_link_request' => 'anonymous: asks for the link',
        'three_brs_admin_magic_link_verify' => 'anonymous: the link itself signs the administrator in',
        'three_brs_admin_passkey_login_verify' => 'anonymous: verifies the assertion that signs in',
        'three_brs_admin_oauth_initiate' => 'anonymous: starts the provider round trip',
        'three_brs_admin_oauth_callback' => 'anonymous: returns from the provider',
        'three_brs_admin_oauth_confirm_link' => 'anonymous: confirms linking before the session exists',
        'three_brs_admin_passkey_login_options' => 'anonymous: hands out the challenge to sign in with',
        // Half-authenticated: the token has no roles until the second factor passes.
        'three_brs_admin_two_factor_recovery_challenge' => 'two-factor in progress',
        // Signed in, but held on one page until the password is changed.
        'three_brs_admin_force_password_change' => 'forced password change',
    ];

    /**
     * Endpoints an administrator uses on their own account. The bundle checks the
     * identity in the token storage, and ownership matters more here than a role.
     */
    protected const OWN_ACCOUNT = [
        'three_brs_admin_two_factor_setup',
        'three_brs_admin_two_factor_recovery_codes',
        'three_brs_admin_two_factor_regenerate_recovery_codes',
        'three_brs_admin_two_factor_disable',
        'three_brs_admin_passkey_index',
        'three_brs_admin_passkey_delete',
        'three_brs_admin_passkey_register_options',
        'three_brs_admin_passkey_register_verify',
        'three_brs_admin_sessions',
        'three_brs_admin_sessions_revoke_others',
        'three_brs_admin_session_revoke',
        'three_brs_admin_social_account_unlink',
        'three_brs_admin_social_accounts',
    ];

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function administrationWideControllerProvider(): iterable
    {
        $root = \dirname(__DIR__, 4);
        /** @var array<string, array{path?: string, controller?: string}> $routes */
        $routes = Yaml::parseFile($root . '/config/routes.yaml');

        foreach ($routes as $name => $route) {
            $path = $route['path'] ?? '';
            $controller = self::resolveController($root, $route['controller'] ?? '');

            if (!str_contains($path, 'sylius_admin.path_name')) {
                continue;
            }

            // What is left after resolution belongs to the bundle, which knows nothing
            // about Sylius roles. Where such a page lists other people's accounts the
            // plugin subclasses it; anything still pointing at the bundle here is a
            // page whose caller has no administrator identity to check yet.
            if (!str_contains($controller, 'SyliusEnterpriseSecurityPlugin\\Controller\\Admin')) {
                continue;
            }

            if (isset(self::NO_ADMIN_IDENTITY_YET[$name]) || \in_array($name, self::OWN_ACCOUNT, true)) {
                continue;
            }

            yield $name => [$controller];
        }
    }

    /**
     * @param class-string $controller
     */
    #[DataProvider('administrationWideControllerProvider')]
    public function testAnAdministrationWideControllerRequiresTheAdminRole(string $controller): void
    {
        $attributes = (new \ReflectionClass($controller))->getAttributes(IsGranted::class);

        self::assertCount(1, $attributes, sprintf('%s carries no IsGranted attribute.', $controller));
        self::assertSame([self::REQUIRED_ROLE], $attributes[0]->getArguments());
    }

    public function testEveryExemptedRouteStillExists(): void
    {
        $root = \dirname(__DIR__, 4);
        /** @var array<string, mixed> $routes */
        $routes = Yaml::parseFile($root . '/config/routes.yaml');

        $exempted = [...array_keys(self::NO_ADMIN_IDENTITY_YET), ...self::OWN_ACCOUNT];
        foreach ($exempted as $name) {
            self::assertArrayHasKey($name, $routes, sprintf('Route "%s" is exempted but no longer exists.', $name));
        }
    }

    /**
     * Routes may name a service id rather than a class; services.yaml says which
     * class stands behind it, and that is where the attribute has to sit.
     */
    protected static function resolveController(string $root, string $controller): string
    {
        if ($controller === '' || str_contains($controller, '\\')) {
            return $controller;
        }

        /** @var array{services?: array<string, array{class?: string}>} $services */
        $services = Yaml::parseFile($root . '/config/services.yaml');

        return $services['services'][$controller]['class'] ?? $controller;
    }

}
