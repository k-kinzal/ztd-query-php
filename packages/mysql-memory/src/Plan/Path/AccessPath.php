<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Path;

/**
 * One step of how a query block computes its rows: what to read and what to do with the rows read.
 *
 * Paths form a tree, as the access paths of the server do; Iterator\Builder turns each path into
 * the iterator that executes it. A path produces rows of a fixed width.
 *
 * @visibility MySqlMemory
 */
interface AccessPath
{
    /**
     * Answers the number of values of each row the path produces.
     */
    public function width(): int;
}
