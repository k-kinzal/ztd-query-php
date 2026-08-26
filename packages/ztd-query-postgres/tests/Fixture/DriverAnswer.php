<?php

declare(strict_types=1);

namespace Tests\Fixture;

use Stringable;

/**
 * Values a driver could hand back that no SQL literal can carry.
 *
 * Nothing in ZTD promises a driver only answers what ZTD can write, so what
 * happens when one does not is worth testing -- and saying so here is what
 * lets a test hand one over without claiming it is a value at all.
 */
final class DriverAnswer
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

    /**
     * Answers something an SQL literal can carry.
     *
     * @return mixed A value inside everything a renderer can write
     */
    public static function renderable(): mixed
    {
        return 'a';
    }


    /**
     * Answers something that says how it spells itself.
     *
     * A driver may hand a column over as an object rather than as its text,
     * and what a renderer does with one is worth saying.
     *
     * @return Stringable A value that spells itself
     */
    public static function stringable(): Stringable
    {
        return new class () implements Stringable {
            /**
             * Answers how this spells itself.
             *
             * @return string The text it stands for
             */
            public function __toString(): string
            {
                return 'stringified';
            }
        };
    }
}
