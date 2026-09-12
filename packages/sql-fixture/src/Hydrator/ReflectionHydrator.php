<?php

declare(strict_types=1);

namespace SqlFixture\Hydrator;

use ReflectionClass;
use ReflectionException;

/**
 * Hydrates rows through constructor parameters or existing object properties.
 */
final class ReflectionHydrator implements HydratorInterface
{
    /**
     * @template T of object
     * @param array<string, mixed> $data
     * @param class-string<T> $className
     * @return T
     * @throws ReflectionException When the requested object cannot be reflected or instantiated
     * @throws HydrationException
     */
    public function hydrate(array $data, string $className): object
    {
        if (!class_exists($className)) {
            throw HydrationException::classNotFound($className);
        }

        $reflection = new ReflectionClass($className);
        $constructor = $reflection->getConstructor();

        if ($constructor !== null && $constructor->getNumberOfParameters() > 0) {
            return (new Reflection\ConstructorHydration())->hydrateViaConstructor($reflection, $constructor->getParameters(), $data);
        }

        return (new Reflection\PropertyHydration())->hydrateViaProperties($reflection, $data);
    }












}
