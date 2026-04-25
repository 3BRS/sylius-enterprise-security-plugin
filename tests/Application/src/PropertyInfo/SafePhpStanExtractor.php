<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\PropertyInfo;

use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\TypeInfo\Exception\InvalidArgumentException;
use Symfony\Component\TypeInfo\Type;

/**
 * Decorator around Symfony's PhpStanExtractor that swallows TypeInfo exceptions
 * caused by upstream PHPDoc tags Symfony cannot parse (e.g. `@template T of Loggable|object`
 * in Gedmo's LoggableListener — Symfony's TypeInfo rejects unions of `object` and a class).
 *
 * Without this wrapper, api_platform metadata factory crashes when scanning Sylius entities.
 */
class SafePhpStanExtractor implements PropertyTypeExtractorInterface
{
    public function __construct(
        protected PropertyTypeExtractorInterface $inner,
    ) {
    }

    public function getType(string $class, string $property, array $context = []): ?Type
    {
        try {
            return $this->inner->getType($class, $property, $context);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    public function getTypes(string $class, string $property, array $context = []): ?array
    {
        try {
            return $this->inner->getTypes($class, $property, $context);
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
