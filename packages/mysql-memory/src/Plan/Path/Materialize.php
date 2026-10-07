<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Path;

use MySqlMemory\Plan\QueryPlan;

/**
 * Reads the rows of a query as a table: a derived table, or a reference to a common table expression.
 *
 * A query that reads no column of the block around it is computed once per statement; a
 * LATERAL one, or a correlated one, again for each row of the block.
 *
 * @visibility MySqlMemory
 */
final class Materialize implements AccessPath
{
    /**
     * @param QueryPlan $query The query whose rows are read
     * @param bool $lateral Whether the query reads the row of the block it is joined in
     */
    public function __construct(public readonly QueryPlan $query, public readonly bool $lateral = false)
    {
    }

    /**
     * Answers the number of columns of the query.
     */
    #[\Override]
    public function width(): int
    {
        return count($this->query->domains);
    }
}
