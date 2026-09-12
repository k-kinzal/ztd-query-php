<?php

declare(strict_types=1);

namespace SqlFixture\Hydrator\Reflection;

use ReflectionClass;
use ReflectionException;
use ReflectionParameter;
use SqlFixture\Hydrator\HydrationException;

/**
 * Hydrates objects through their declared constructor.
 *
 */
final class ConstructorHydration
{
    /**
     * @template T of object
     * @param ReflectionClass<T> $reflection
     * @param array<ReflectionParameter> $parameters
     * @param array<string, mixed> $data
     * @return T
     * @throws ReflectionException When the requested object cannot be reflected or instantiated
     * @throws HydrationException
     */
    public function hydrateViaConstructor(
        ReflectionClass $reflection,
        array $parameters,
        array $data,
    ): object {
        $args = [];

        foreach ($parameters as $parameter) {
            $paramName = $parameter->getName();
            $snakeName = (new PropertyNames())->toSnakeCase($paramName);

            if (array_key_exists($paramName, $data)) {
                $value = $data[$paramName];
            } elseif (array_key_exists($snakeName, $data)) {
                $value = $data[$snakeName];
            } elseif ($parameter->isDefaultValueAvailable()) {
                $value = $parameter->getDefaultValue();
            } elseif ($parameter->allowsNull()) {
                $value = null;
            } else {
                throw HydrationException::constructorParameterMissing(
                    $reflection->getName(),
                    $paramName,
                );
            }

            $args[] = (new ValueConversion())->castValue($value, $parameter->getType());
        }

        return $reflection->newInstanceArgs($args);
    }
}
