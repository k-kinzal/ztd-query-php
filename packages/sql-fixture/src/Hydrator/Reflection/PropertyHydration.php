<?php

declare(strict_types=1);

namespace SqlFixture\Hydrator\Reflection;

use ReflectionClass;
use ReflectionException;
use ReflectionProperty;

/**
 * Hydrates objects by assigning existing properties.
 *
 */
final class PropertyHydration
{
    /**
     * @template T of object
     * @param ReflectionClass<T> $reflection
     * @param array<string, mixed> $data
     * @return T
     * @throws ReflectionException When the requested object cannot be reflected or instantiated
     */
    public function hydrateViaProperties(ReflectionClass $reflection, array $data): object
    {
        $instance = $reflection->newInstanceWithoutConstructor();

        foreach ($data as $key => $value) {
            $propertyName = (new PropertyNames())->toCamelCase($key);

            if (!$reflection->hasProperty($propertyName) && !$reflection->hasProperty($key)) {
                continue;
            }

            $property = $reflection->hasProperty($propertyName)
                ? $reflection->getProperty($propertyName)
                : $reflection->getProperty($key);

            $this->setProperty($instance, $property, $value);
        }

        return $instance;
    }

    /**
     * Assigns property.
     */
    public function setProperty(object $instance, ReflectionProperty $property, mixed $value): void
    {
        $property->setValue($instance, (new ValueConversion())->castValue($value, $property->getType()));
    }
}
