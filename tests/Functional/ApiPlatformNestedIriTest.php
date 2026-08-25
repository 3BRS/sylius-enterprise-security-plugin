<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use Sylius\Component\Core\Model\ChannelPricing;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Model\ProductImage;
use Sylius\Component\Core\Model\ProductVariant;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\HttpKernel\Kernel as SymfonyKernel;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\TypeInfo\Type;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Kernel;

/**
 * Regression test for the api-platform 4.3.x property-info workaround.
 *
 * The plugin pulls dependencies (e.g. web-auth/webauthn-lib) whose generic
 * `@template T of object` PHPDoc makes api-platform's property-metadata scanner
 * build an `object|ClassName` union that Symfony TypeInfo rejects, aborting
 * route/metadata warmup. The documented SafePhpStanExtractor decorator swallows
 * that exception.
 *
 * The workaround must NOT have the side effect of neutralising legitimate
 * Doctrine relation types: api-platform needs the concrete related class to
 * build nested IRIs (e.g. /shop/products/{code}/images/{id}). If a relation
 * such as Image::$owner resolves to a bare `object` instead of the concrete
 * Product, IRI generation breaks with "Unable to generate an IRI for ...".
 *
 * These two tests pin both halves: warmup survives the poison PHPDoc, and a
 * Doctrine relation still resolves to its concrete class. They run across the
 * api-platform versions in the CI matrix so the guarantee holds on the lowest
 * supported 4.3.x release too.
 */
class ApiPlatformNestedIriTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Booting the Sylius kernel registers an exception handler that is not
        // popped on shutdown; restore it so PHPUnit does not flag the test as
        // risky for leaking global handler state.
        restore_exception_handler();
    }

    public function testApiPlatformMetadataWarmsUpDespitePoisonPhpDoc(): void
    {
        self::bootKernel(['environment' => 'test', 'debug' => true]);

        /** @var RouterInterface $router */
        $router = self::getContainer()->get('router');

        // Building the full route collection runs api-platform's route loader,
        // which scans every resource's property metadata — the code path that
        // throws "Cannot create union with both object and class type" without
        // the SafePhpStanExtractor decorator in place.
        $routes = $router->getRouteCollection();

        self::assertGreaterThan(0, $routes->count());
        self::assertNotNull(
            $routes->get('sylius_api_shop_product_get_collection'),
            'API Platform routes must warm up; the shop product collection route is missing.',
        );
    }

    /**
     * @return iterable<string, array{class-string, string, class-string}>
     */
    public static function doctrineRelationProvider(): iterable
    {
        // Image::$owner backs the nested /shop/products/{code}/images/{id} IRI.
        yield 'ProductImage::$owner' => [ProductImage::class, 'owner', Product::class];
        // ChannelPricing::$productVariant backs the nested
        // /admin/product-variants/{code}/channel-pricings/{channelCode} IRI.
        yield 'ChannelPricing::$productVariant' => [ChannelPricing::class, 'productVariant', ProductVariant::class];
    }

    /**
     * @param class-string $class
     * @param class-string $expectedConcreteClass
     */
    #[DataProvider('doctrineRelationProvider')]
    public function testDoctrineRelationResolvesToConcreteClassForNestedIri(string $class, string $property, string $expectedConcreteClass): void
    {
        // The workaround this pins is TypeInfo's, and property-info grew getType()
        // alongside it in Symfony 7.1. Below that the extractor speaks only the
        // legacy getTypes(), and there is no union for anything to reject.
        if (version_compare(SymfonyKernel::VERSION, '7.1', '<')) {
            self::markTestSkipped('Property-info getType() and the TypeInfo union it guards arrived in Symfony 7.1.');
        }

        self::bootKernel(['environment' => 'test', 'debug' => true]);

        /** @var PropertyTypeExtractorInterface $propertyInfo */
        $propertyInfo = self::getContainer()->get('api_platform.property_info');

        $type = $propertyInfo->getType($class, $property);

        self::assertInstanceOf(Type::class, $type, sprintf('No type resolved for %s::$%s.', $class, $property));

        // Not assertStringContainsString: every concrete Sylius model is a literal
        // prefix of its own interface, so the degraded "…ProductVariantInterface|null"
        // contains "…ProductVariant" and satisfies exactly the outcome this rejects.
        self::assertDoesNotMatchRegularExpression(
            '/\b' . preg_quote($expectedConcreteClass, '/') . 'Interface\b/',
            (string) $type,
            sprintf(
                '%s::$%s resolved to the interface rather than %s, so api-platform cannot build the nested IRI.',
                $class,
                $property,
                $expectedConcreteClass,
            ),
        );
        self::assertStringContainsString(
            $expectedConcreteClass,
            (string) $type,
            sprintf(
                '%s::$%s must resolve to the concrete %s so api-platform can build the nested IRI; '
                . 'got "%s" instead (a bare "object"/interface means the workaround clobbered the Doctrine relation type).',
                $class,
                $property,
                $expectedConcreteClass,
                (string) $type,
            ),
        );
    }
}
