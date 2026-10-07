<?php

declare(strict_types=1);

namespace MySqlMemory\Iterator\Source;

use MySqlMemory\Iterator\RowIterator;
use MySqlMemory\Evaluation\Frame;
use Override;

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
    #[Override]
    public function init(Frame $frame): void
    {
    }

    /**
     * Answers null.
     */
    #[Override]
    public function read(): ?array
    {
        return null;
    }
}
