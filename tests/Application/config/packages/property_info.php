<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

/*
 * api-platform 4.3.x nested-IRI workaround (see README §Troubleshooting).
 *
 * api-platform's constructor property-info extractor (the default from Symfony
 * 8.0) runs BEFORE the Doctrine extractor and resolves relation properties such
 * as Image::$owner to a bare `object` from their `@var object|null` PHPDoc,
 * shadowing the concrete Doctrine type. That breaks nested IRI generation
 * (e.g. /shop/products/{code}/images/{id}).
 *
 * Disabling it lets the Doctrine extractor win for relations. Below Symfony 8
 * that is already the default, so the setting exists to pin the behaviour and to
 * answer the deprecation Symfony 7.3 raises for leaving it unstated.
 *
 * The option itself only exists from 7.3, and naming it on 6.4 aborts container
 * compilation — hence the version guard rather than a YAML file.
 */
return static function (ContainerConfigurator $container): void {
    if (version_compare(Kernel::VERSION, '7.3', '<')) {
        return;
    }

    $container->extension('framework', [
        'property_info' => ['with_constructor_extractor' => false],
    ]);
};
