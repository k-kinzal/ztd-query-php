<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Pdo\Driver;

/**
 * The fetch mode a caller set on a statement, remembered with its arguments.
 *
 * ZTD prepares a statement again every time it is executed, and a statement
 * prepared again is back on the connection's default mode. Remembering what
 * was asked for is what lets the mode be set again before the rows are read.
 */
final class PdoFetchMode
{
    /**
     * Remembers one fetch mode.
     *
     * @param int $mode One of PDO::FETCH_*
     * @param list<mixed> $arguments The rest of what setFetchMode() was given, as that mode reads them
     */
    public function __construct(
        public readonly int $mode,
        public readonly array $arguments = [],
    ) {
    }
}
