<?php

declare(strict_types=1);

namespace Tests\Fixture;

/**
 * Values a driver could hand back that no SQL literal can carry.
 *
 * Nothing in ZTD promises a driver only answers what ZTD can write, so what
 * happens when one does not is worth testing -- and saying so here is what
 * lets a test hand one over without claiming it is a value at all.
 */
final class DriverValues
{
    /**
     * Answers something no SQL literal can carry.
     *
     * @return mixed A value outside everything a renderer can write
     */
    public static function unsupported(): mixed
    {
        return [];
    }
}
