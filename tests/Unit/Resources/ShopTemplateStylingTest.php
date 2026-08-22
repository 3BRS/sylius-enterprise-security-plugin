<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Resources;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Storefront templates get written by copying an admin one, and the two layouts
 * do not offer the same things. Sylius builds the admin on Tabler and the shop
 * on plain Bootstrap, and only the admin layout declares a `body_class` block —
 * the shop layout carries a `#body_classes` twig hook instead. Neither
 * difference announces itself: an override of a block the layout never declares
 * is dropped in silence, and a class no stylesheet defines simply does nothing,
 * so the page renders unstyled rather than broken.
 */
class ShopTemplateStylingTest extends TestCase
{
    /**
     * Tabler ships these; Sylius' shop assets do not.
     */
    protected const ADMIN_ONLY_CLASSES = ['page-center', 'container-tight', 'card-md'];

    /**
     * @return iterable<string, array{string}>
     */
    public static function shopTemplateProvider(): iterable
    {
        $root = \dirname(__DIR__, 3) . '/src/Resources/views/Shop';
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

        foreach ($files as $file) {
            if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'twig') {
                continue;
            }

            yield 'Shop/' . str_replace($root . '/', '', $file->getPathname()) => [$file->getPathname()];
        }
    }

    #[DataProvider('shopTemplateProvider')]
    public function testAStorefrontTemplateStylesItselfForTheShopLayout(string $path): void
    {
        $contents = (string) file_get_contents($path);

        self::assertDoesNotMatchRegularExpression(
            '/{%-?\s*block\s+body_class\s/',
            $contents,
            'The shop layout declares no body_class block, so this override is dropped.',
        );

        foreach (self::ADMIN_ONLY_CLASSES as $class) {
            self::assertStringNotContainsString(
                $class,
                $contents,
                sprintf('"%s" comes from Tabler, which the storefront does not load.', $class),
            );
        }
    }
}
