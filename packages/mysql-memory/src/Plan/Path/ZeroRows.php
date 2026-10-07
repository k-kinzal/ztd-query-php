<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Path;

/**
 * Produces no rows, of a fixed width: the input of a block whose condition is known to be false.
 *
 * @visibility MySqlMemory
 */
final class ZeroRows implements AccessPath
{
    /**
     * @param int $width The width of the rows not produced
     */
    public function __construct(public readonly int $width)
    {
    }

    /**
     * Answers the width.
     */
    #[\Override]
    public function width(): int
    {
        return $this->width;
    }
}
