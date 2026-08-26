<?php

declare(strict_types=1);

namespace SqlFixture\Hydrator;

/**
 * Turns a generated row into an object.
 *
 * A fixture is more useful as the type the code under test expects than as an
 * array, so a row may be handed to a hydrator on the way out.
 */
interface HydratorInterface
{
    /**
     * Hydrate data into an object of the given class.
     *
     * @template T of object
     * @param array<string, mixed> $data
     * @param class-string<T> $className
     * @return T
     *
     * @throws HydrationException
     */
    public function hydrate(array $data, string $className): object;
}
