<?php

declare(strict_types=1);

namespace MySqlMemory\Iterator;

use MySqlMemory\Evaluation\Frame;

/**
 * Answers no row.
 *
 * @visibility MySqlMemory
 */
final class ZeroRowsIterator implements RowIterator
{
    /**
     * Does nothing.
     */
    #[\Override]
    public function init(Frame $frame): void
    {
    }

    /**
     * Answers null.
     */
    #[\Override]
    public function read(): ?array
    {
        return null;
    }
}
