<?php

declare(strict_types=1);

namespace SqlFixture\Hydrator;

use Override;
use ReflectionClass;
use ReflectionException;

/**
 * Builds an object from a fixture row using whichever route the class allows.
 *
 * A class that declares constructor parameters is built through them, so it is
 * valid from the moment it exists. A class that declares none is built without
 * calling its constructor at all and has the row assigned onto its properties.
 * Which of the two applies is the only decision made here; each route answers
 * for itself.
 */
final class ReflectionHydrator implements HydratorInterface
{
    /**
     * @param ConstructorHydration $throughConstructor Builds a class that declares what it needs
     * @param PropertyHydration $throughProperties Builds a class that declares nothing
     */
    public function __construct(
        private readonly ConstructorHydration $throughConstructor = new ConstructorHydration(),
        private readonly PropertyHydration $throughProperties = new PropertyHydration(),
    ) {
    }

    /**
     * Builds an object of the named class from a row.
     *
     * @template T of object
     * @param array<string, mixed> $data Row to build it from
     * @param class-string<T> $className Class to build
     *
     * @return T The object
     *
     * @throws HydrationException When the class does not exist, cannot be built, or a parameter has nowhere to get a value
     */
    #[Override]
    public function hydrate(array $data, string $className): object
    {
        if (!class_exists($className)) {
            throw HydrationException::classNotFound($className);
        }

        $constructor = (new ReflectionClass($className))->getConstructor();

        try {
            return $constructor !== null && $constructor->getNumberOfParameters() > 0
                ? $this->throughConstructor->hydrate($className, $data)
                : $this->throughProperties->hydrate($className, $data);
        } catch (ReflectionException $cause) {
            throw HydrationException::notInstantiable($className, $cause);
        }
    }
}
