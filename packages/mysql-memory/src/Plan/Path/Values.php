<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Path;

use MySqlMemory\Evaluation\Evaluable;
use Override;

/**
 * Produces the rows a VALUES list or a table value constructor writes.
 *
 * @visibility MySqlMemory
 */
final class Values implements AccessPath
{
    /**
     * @param list<list<Evaluable>> $rows The expressions of each row
     * @param int $width The number of values of each row
     */
    public function __construct(public readonly array $rows, public readonly int $width)
    {
    }

    /**
     * Answers the number of values of each row.
     */
    #[Override]
    public function width(): int
    {
        return $this->width;
    }
}
