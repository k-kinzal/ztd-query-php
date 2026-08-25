<?php

declare(strict_types=1);

namespace SqlFixture\Hydrator;

use ReflectionClass;
use ReflectionException;
use ReflectionProperty;

/**
 * Builds an object by assigning the row onto its properties.
 *
 * This is the route for an object that takes nothing in its constructor. The
 * constructor is skipped entirely rather than called with nothing, because an
 * object that declares no parameters may still do work there that a fixture
 * has no business triggering.
 *
 * A column with no property to go to is passed over rather than reported: a
 * row carries every column of its table, and an object is free to model only
 * the part of it that it cares about.
 */
final class PropertyHydration
{
    /**
     * @param DeclaredTypeCast $cast Reads a value as the type its property declares
     */
    public function __construct(private readonly DeclaredTypeCast $cast = new DeclaredTypeCast())
    {
    }

    /**
     * Builds the object from the row.
     *
     * A column is looked for under the property spelling of its name first,
     * and then under the name as written, so an object may spell a property
     * either way.
     *
     * @template T of object
     * @param class-string<T> $className Class being built
     * @param array<string, mixed> $data Row to build it from
     *
     * @return T The object
     *
     * @throws ReflectionException When the class cannot be instantiated
     */
    public function hydrate(string $className, array $data): object
    {
        $reflection = new ReflectionClass($className);
        $instance = $reflection->newInstanceWithoutConstructor();

        foreach ($data as $column => $value) {
            $property = $this->propertyFor($className, (string) $column);
            if ($property === null) {
                continue;
            }
            $property->setValue($instance, $this->cast->of($value, $property->getType()));
        }

        return $instance;
    }

    /**
     * Answers the property a column is assigned to.
     *
     * @param class-string $className Class being built
     * @param string $column Column name as the row spells it
     *
     * @return ReflectionProperty|null The property, or null when the object models no such column
     */
    public function propertyFor(string $className, string $column): ?ReflectionProperty
    {
        $reflection = new ReflectionClass($className);
        foreach ([PropertyName::toCamelCase($column), $column] as $name) {
            if ($reflection->hasProperty($name)) {
                return $reflection->getProperty($name);
            }
        }

        return null;
    }
}
