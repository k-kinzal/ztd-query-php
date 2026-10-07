<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Path;

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
     */
    public function __construct(public readonly AccessPath $input, public readonly ?int $count, public readonly int $offset = 0)
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
