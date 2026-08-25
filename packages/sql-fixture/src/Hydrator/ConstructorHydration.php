<?php

declare(strict_types=1);

namespace SqlFixture\Hydrator;

use ReflectionClass;
use ReflectionException;

/**
 * Builds an object by passing the row to its constructor.
 *
 * This is the route for an object that declares what it needs, and it is
 * preferred over assigning properties because it lets the object stay valid
 * from the moment it exists. Each parameter is looked for in the row under its
 * own name and under the column spelling of that name; failing both, the
 * parameter's own default stands, and a nullable parameter is given null. A
 * parameter with none of those has nowhere to get a value, and the object
 * cannot be built.
 */
final class ConstructorHydration
{
    /**
     * @param DeclaredTypeCast $cast Reads a value as the type its parameter declares
     */
    public function __construct(private readonly DeclaredTypeCast $cast = new DeclaredTypeCast())
    {
    }

    /**
     * Builds the object from the row.
     *
     * @template T of object
     * @param class-string<T> $className Class being built
     * @param array<string, mixed> $data Row to build it from
     *
     * @return T The object
     *
     * @throws HydrationException When a parameter has no value, no default, and is not nullable
     * @throws ReflectionException When the class cannot be instantiated
     */
    public function hydrate(string $className, array $data): object
    {
        $reflection = new ReflectionClass($className);
        $constructor = $reflection->getConstructor();
        $arguments = [];

        foreach ($constructor?->getParameters() ?? [] as $parameter) {
            $name = $parameter->getName();
            $columnName = PropertyName::toSnakeCase($name);

            if (array_key_exists($name, $data)) {
                $value = $data[$name];
            } elseif (array_key_exists($columnName, $data)) {
                $value = $data[$columnName];
            } elseif ($parameter->isDefaultValueAvailable()) {
                $value = $parameter->getDefaultValue();
            } elseif ($parameter->allowsNull()) {
                $value = null;
            } else {
                throw HydrationException::constructorParameterMissing($reflection->getName(), $name);
            }

            $arguments[] = $this->cast->of($value, $parameter->getType());
        }

        return $reflection->newInstanceArgs($arguments);
    }
}
