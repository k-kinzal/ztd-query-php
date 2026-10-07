<?php

declare(strict_types=1);

namespace MySqlMemory\Iterator\Source;

use MySqlMemory\Iterator\RowIterator;
use MySqlMemory\Evaluation\Frame;
use Override;

/**
 * Answers one empty row.
 *
 * @visibility MySqlMemory
 */
final class SingleRowIterator implements RowIterator
{
    private bool $done = false;

    /**
     * Restarts the iteration.
     */
    #[Override]
    public function init(Frame $frame): void
    {
        $this->done = false;
    }

    /**
     * Answers the empty row once.
     */
    #[Override]
    public function read(): ?array
    {
        if ($this->done) {
            return null;
        }
        $this->done = true;

        return [];
    }
}
