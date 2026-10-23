<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Leaf;

use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Domain;
use Override;

/**
 * A column of a row: the value at a position of the row of a block, that many blocks out.
 *
 * @visibility MySqlMemory
 */
final class ColumnRead implements Evaluable
{
    /**
     * @param Domain $domain The domain of the column
     * @param int $position The position of the column in the row of its block
     * @param int $depth The number of blocks out the column's block is; zero for the current block
     */
    public function __construct(public readonly Domain $domain, public readonly int $position, public readonly int $depth = 0)
    {
    }

    /**
     * Answers the domain of the column.
     */
    #[Override]
    public function domain(): Domain
    {
        return $this->domain;
    }

    /**
     * Reads the column from the row of its block.
     */
    #[Override]
    public function evaluate(Frame $frame): int|float|string|null
    {
        return ($this->depth === 0 ? $frame : $frame->out($this->depth))->row[$this->position] ?? null;
    }
}
