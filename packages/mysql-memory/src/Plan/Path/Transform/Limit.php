<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Path\Transform;

use MySqlMemory\Plan\Path\AccessPath;
use Override;

/**
 * Skips a number of rows of its input and passes at most a number of the rest.
 *
 * @visibility MySqlMemory
 */
final class Limit implements AccessPath
{
    /**
     * @param AccessPath $input The rows limited
     * @param int|null $count The most rows passed, or null for no bound
     * @param int $offset The rows skipped first
     * @param bool $countFound Whether a bound before projection preserves its input count for SQL_CALC_FOUND_ROWS
     */
    public function __construct(public readonly AccessPath $input, public readonly ?int $count, public readonly int $offset = 0, public readonly bool $countFound = false)
    {
    }

    /**
     * Answers the width of the input.
     */
    #[Override]
    public function width(): int
    {
        return $this->input->width();
    }
}
