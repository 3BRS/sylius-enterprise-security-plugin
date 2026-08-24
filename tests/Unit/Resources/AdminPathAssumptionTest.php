<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Resources;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Sylius takes the administration path from SYLIUS_ADMIN_ROUTING_PATH_NAME, so
 * "/admin" is a default rather than a fact. A template that decides anything by
 * comparing the URL against it — which layout to extend, which route to link —
 * quietly takes the wrong branch on an installation that renamed the path, and
 * renders without error while doing it.
 *
 * Templates have route names and the parameter itself to work from, so no
 * template needs the literal; the two IP listeners keep it as a constructor
 * default the service definitions override, which is why this looks at
 * templates only.
 */
class AdminPathAssumptionTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function templateProvider(): iterable
    {
        $root = \dirname(__DIR__, 3) . '/templates';
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

        foreach ($files as $file) {
            if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'twig') {
                continue;
            }

            yield str_replace($root . '/', '', $file->getPathname()) => [$file->getPathname()];
        }
    }

    #[DataProvider('templateProvider')]
    public function testATemplateDoesNotDecideAnythingByTheDefaultAdminPath(string $path): void
    {
        $contents = (string) file_get_contents($path);

        // Comments explain the rule; only code is held to it.
        $code = (string) preg_replace('/\{#.*?#\}/s', '', $contents);

        self::assertDoesNotMatchRegularExpression(
            "#['\"]/admin#",
            $code,
            'The administration path is configurable; use the route name or %sylius_admin.path_name%.',
        );
    }
}
