<?php

declare(strict_types=1);

namespace SqlFixture\Hydrator;

use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;

final class ReflectionHydrator implements HydratorInterface
{
    /**
     * @template T of object
     * @param array<string, mixed> $data
     * @param class-string<T> $className
     * @return T
     */
    public function hydrate(array $data, string $className): object
    {
        if (!class_exists($className)) {
            throw HydrationException::classNotFound($className);
        }

        $reflection = new ReflectionClass($className);
        $constructor = $reflection->getConstructor();

        if ($constructor !== null && $constructor->getNumberOfParameters() > 0) {
            return $this->hydrateViaConstructor($reflection, $constructor->getParameters(), $data);
        }

        return $this->hydrateViaProperties($reflection, $data);
    }

    /**
     * @template T of object
     * @param ReflectionClass<T> $reflection
     * @param array<ReflectionParameter> $parameters
     * @param array<string, mixed> $data
     * @return T
     */
    private function hydrateViaConstructor(
        ReflectionClass $reflection,
        array $parameters,
        array $data,
    ): object {
        $args = [];

        foreach ($parameters as $parameter) {
            $paramName = $parameter->getName();
            $snakeName = $this->toSnakeCase($paramName);

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

            $args[] = $this->castValue($value, $parameter->getType());
        }

        return $reflection->newInstanceArgs($args);
    }

    /**
     * @template T of object
     * @param ReflectionClass<T> $reflection
     * @param array<string, mixed> $data
     * @return T
     */
    private function hydrateViaProperties(ReflectionClass $reflection, array $data): object
    {
        $instance = $reflection->newInstanceWithoutConstructor();

        foreach ($data as $key => $value) {
            $propertyName = $this->toCamelCase($key);

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

    private function setProperty(object $instance, ReflectionProperty $property, mixed $value): void
    {
        $property->setValue($instance, $this->castValue($value, $property->getType()));
    }

    private function castValue(mixed $value, ?\ReflectionType $type): mixed
    {
        if ($value === null) {
            return null;
        }

        if (!$type instanceof ReflectionNamedType) {
            return $value;
        }

        $typeName = $type->getName();

        return match ($typeName) {
            'int' => is_numeric($value) ? (int) $value : $value,
            'float' => is_numeric($value) ? (float) $value : $value,
            'string' => is_scalar($value) ? (string) $value : $value,
            'bool' => (bool) $value,
            'array' => is_string($value) ? json_decode($value, true) ?? [$value] : (array) $value,
            default => $value,
        };
    }

    private function toSnakeCase(string $input): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $input) ?? $input);
    }

    private function toCamelCase(string $input): string
    {
        $result = str_replace('_', '', ucwords($input, '_'));
        return lcfirst($result);
    }
}
