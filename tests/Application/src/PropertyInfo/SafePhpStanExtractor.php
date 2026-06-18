<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\PropertyInfo;

use phpDocumentor\Reflection\DocBlock;
use Symfony\Component\PropertyInfo\Extractor\ConstructorArgumentTypeExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyAccessExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyDescriptionExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyDocBlockExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyInitializableExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyListExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyReadInfo;
use Symfony\Component\PropertyInfo\PropertyReadInfoExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyWriteInfo;
use Symfony\Component\PropertyInfo\PropertyWriteInfoExtractorInterface;
use Symfony\Component\PropertyInfo\Type as LegacyType;
use Symfony\Component\TypeInfo\Exception\InvalidArgumentException;
use Symfony\Component\TypeInfo\Type;

/**
 * Decorator around any Symfony property-info extractor that swallows TypeInfo
 * exceptions caused by upstream PHPDoc tags Symfony cannot parse (e.g.
 * `@template T of Loggable|object` in Gedmo's LoggableListener, or
 * `@template T of object` paired with `@var list<T>` — TypeInfo rejects
 * unions like `object|ClassName` when api-platform's `LegacyTypeConverter`
 * widens generic bounds during property metadata scanning).
 *
 * Implements every property-info extractor interface and forwards to the
 * inner extractor — both PhpDocExtractor and ReflectionExtractor in the chain
 * can surface the poison type, and any uncaught throw aborts api-platform's
 * route loader. Forwarded methods are no-ops when the inner does not
 * implement the corresponding interface.
 *
 * Despite the name, this decorator is reused for all property-info extractors;
 * the name is kept for git history.
 *
 * TODO: Remove once api-platform / Symfony TypeInfo fixes the regression
 *       introduced in api-platform 4.3.6 (LegacyTypeConverter rejecting
 *       `object|ClassName` unions widened from generic `@template` bounds).
 */
class SafePhpStanExtractor implements
    PropertyTypeExtractorInterface,
    ConstructorArgumentTypeExtractorInterface,
    PropertyDescriptionExtractorInterface,
    PropertyAccessExtractorInterface,
    PropertyInitializableExtractorInterface,
    PropertyListExtractorInterface,
    PropertyDocBlockExtractorInterface,
    PropertyReadInfoExtractorInterface,
    PropertyWriteInfoExtractorInterface
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
            $types = $this->inner->getTypes($class, $property, $context);
        } catch (InvalidArgumentException) {
            return null;
        }

        return $types === null ? null : $this->stripGenericObjectWhenMixedWithClass($types);
    }

    public function getTypeFromConstructor(string $class, string $property): ?Type
    {
        if (!$this->inner instanceof ConstructorArgumentTypeExtractorInterface) {
            return null;
        }

        try {
            return $this->inner->getTypeFromConstructor($class, $property);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    public function getTypesFromConstructor(string $class, string $property): ?array
    {
        if (!$this->inner instanceof ConstructorArgumentTypeExtractorInterface) {
            return null;
        }

        try {
            $types = $this->inner->getTypesFromConstructor($class, $property);
        } catch (InvalidArgumentException) {
            return null;
        }

        return $types === null ? null : $this->stripGenericObjectWhenMixedWithClass($types);
    }

    public function getShortDescription(string $class, string $property, array $context = []): ?string
    {
        return $this->inner instanceof PropertyDescriptionExtractorInterface
            ? $this->inner->getShortDescription($class, $property, $context)
            : null;
    }

    public function getLongDescription(string $class, string $property, array $context = []): ?string
    {
        return $this->inner instanceof PropertyDescriptionExtractorInterface
            ? $this->inner->getLongDescription($class, $property, $context)
            : null;
    }

    public function isReadable(string $class, string $property, array $context = []): ?bool
    {
        return $this->inner instanceof PropertyAccessExtractorInterface
            ? $this->inner->isReadable($class, $property, $context)
            : null;
    }

    public function isWritable(string $class, string $property, array $context = []): ?bool
    {
        return $this->inner instanceof PropertyAccessExtractorInterface
            ? $this->inner->isWritable($class, $property, $context)
            : null;
    }

    public function isInitializable(string $class, string $property, array $context = []): ?bool
    {
        return $this->inner instanceof PropertyInitializableExtractorInterface
            ? $this->inner->isInitializable($class, $property, $context)
            : null;
    }

    public function getProperties(string $class, array $context = []): ?array
    {
        return $this->inner instanceof PropertyListExtractorInterface
            ? $this->inner->getProperties($class, $context)
            : null;
    }

    public function getDocBlock(string $class, string $property): ?DocBlock
    {
        return $this->inner instanceof PropertyDocBlockExtractorInterface
            ? $this->inner->getDocBlock($class, $property)
            : null;
    }

    public function getReadInfo(string $class, string $property, array $context = []): ?PropertyReadInfo
    {
        return $this->inner instanceof PropertyReadInfoExtractorInterface
            ? $this->inner->getReadInfo($class, $property, $context)
            : null;
    }

    public function getWriteInfo(string $class, string $property, array $context = []): ?PropertyWriteInfo
    {
        return $this->inner instanceof PropertyWriteInfoExtractorInterface
            ? $this->inner->getWriteInfo($class, $property, $context)
            : null;
    }

    /**
     * Drop the bare `object` legacy type when a specific class type is also
     * present — otherwise api-platform tries to build `union(object, Foo)`
     * which the TypeInfo component rejects.
     *
     * @param list<LegacyType> $types
     *
     * @return list<LegacyType>
     */
    protected function stripGenericObjectWhenMixedWithClass(array $types): array
    {
        $hasGenericObject = false;
        $hasSpecificObject = false;
        foreach ($types as $type) {
            if ($type->getBuiltinType() !== LegacyType::BUILTIN_TYPE_OBJECT) {
                continue;
            }
            if ($type->getClassName() === null) {
                $hasGenericObject = true;
            } else {
                $hasSpecificObject = true;
            }
        }

        if (!$hasGenericObject || !$hasSpecificObject) {
            return $types;
        }

        return array_values(array_filter(
            $types,
            static fn (LegacyType $type): bool => $type->getBuiltinType() !== LegacyType::BUILTIN_TYPE_OBJECT || $type->getClassName() !== null,
        ));
    }
}
