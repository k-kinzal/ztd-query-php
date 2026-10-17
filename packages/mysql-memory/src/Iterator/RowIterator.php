<?php

declare(strict_types=1);

namespace MySqlMemory\Iterator;

use MySqlMemory\Evaluation\Frame;

/**
 * Executes one access path: started for a frame, then read row by row until it answers null.
 *
 * @visibility MySqlMemory
 */
interface RowIterator
{
    /**
     * Starts or restarts the iteration; expressions are evaluated in the frame.
     *
     * @throws \MySqlMemory\Error\SqlError When computing the rows is an error
     */
    public function init(Frame $frame): void;

    /**
     * Answers the next row, or null when there are no more.
     *
     * @return list<int|float|string|null>|null
     *
     * @throws \MySqlMemory\Error\SqlError When computing the row is an error
     */
    public function read(): ?array;
}
