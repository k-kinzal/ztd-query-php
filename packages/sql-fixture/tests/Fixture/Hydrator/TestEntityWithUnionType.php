<?php

declare(strict_types=1);

namespace Tests\Fixture\Hydrator;

/**
 * An entity whose property declares more than one type, so nothing says which to read a value as.
 */
class TestEntityWithUnionType
{
    /**
     * Value the row carries, as either of the types it may hold
     */
    public int|string $value = 0;
}
