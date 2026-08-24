<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Resources;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Some Symfony services exist only when the application's security configuration
 * asks for them. Referencing one of those with a plain '@' makes the plugin
 * refuse to compile the container of an application that does not — not a
 * degraded feature, a site that will not boot, and switching the feature off
 * does not help because the service definition is loaded either way.
 *
 * Each id below is listed with the configuration that brings it into existence.
 */
class ConditionalServiceReferenceTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function conditionalServiceProvider(): iterable
    {
        // RememberMeFactory::createAuthenticator() loads the definition, and it runs
        // only for a firewall carrying a `remember_me` key.
        yield 'remember-me handler' => [
            'security.authenticator.firewall_aware_remember_me_handler',
            'a firewall configuring remember_me',
        ];
    }

    #[DataProvider('conditionalServiceProvider')]
    public function testAConditionalServiceIsReferencedOptionally(string $serviceId, string $condition): void
    {
        $services = (string) file_get_contents(\dirname(__DIR__, 3) . '/config/services.yaml');

        $hard = substr_count($services, sprintf("'@%s'", $serviceId));

        self::assertSame(
            0,
            $hard,
            sprintf(
                '"%s" exists only with %s, so it has to be referenced as \'@?%s\'.',
                $serviceId,
                $condition,
                $serviceId,
            ),
        );
    }
}
