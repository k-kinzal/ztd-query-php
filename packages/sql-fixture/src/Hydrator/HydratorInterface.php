<?php

declare(strict_types=1);

namespace SqlFixture\Hydrator;

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
