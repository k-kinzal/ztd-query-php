<?php

declare(strict_types=1);

namespace SqlFixture\Fixture;

use RuntimeException;

/**
 * Reports an attempt to change a set after it was generated.
 *
 * A set is what one generation produced, and the rows in it are the rows the
 * plan and the schema together called for. Changing one afterwards would leave
 * the set describing rows that were never generated, so it is refused rather
 * than accepted quietly. Refusing is declared behaviour, so it is reported at
 * runtime and a caller can catch it.
 */
final class ReadOnlySetException extends RuntimeException
{
    /**
     * Reports an attempt to write an entry.
     *
     * @param int|string $table Table the caller wrote to, or its position
     *
     * @return self Exception naming what the caller tried to change
     */
    public static function cannotWrite(int|string $table): self
    {
        return new self(sprintf(
            'Cannot set "%s" on a generated fixture set: it is what one generation produced.',
            (string) $table
        ));
    }

    /**
     * Reports an attempt to remove an entry.
     *
     * @param int|string $table Table the caller removed, or its position
     *
     * @return self Exception naming what the caller tried to remove
     */
    public static function cannotRemove(int|string $table): self
    {
        return new self(sprintf(
            'Cannot remove "%s" from a generated fixture set: it is what one generation produced.',
            (string) $table
        ));
    }
}
