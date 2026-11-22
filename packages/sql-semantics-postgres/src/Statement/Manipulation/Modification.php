<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation;

use SqlSemantics\Platform\PostgreSql\Model\Statement\Query;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;
use SqlSemantics\Platform\PostgreSql\Statement\Query\CommonTables;

/**
 * A data-modifying statement: INSERT, UPDATE, DELETE or MERGE.
 *
 * Each is a statement and also a query whose rows are those of its
 * RETURNING list, without columns when it has none, so that it can be the
 * body of a common table expression, the query of a prepared statement or
 * of COPY, or a statement nested in another one. Deriving it as a query
 * (`Derivation::query()`) derives every part against the given outer
 * environment and records no root output; deriving it as a statement
 * records its RETURNING rows as the output of the root.
 * Source: https://www.postgresql.org/docs/17/queries-with.html#QUERIES-WITH-MODIFYING.
 *
 * @visibility SqlSemantics
 */
interface Modification extends Statement, Query
{
    /**
     * Tells whether the statement returns rows: whether it has a RETURNING list.
     */
    public function returnsRows(): bool;

    /**
     * Answers the WITH clause written before the statement, or null.
     */
    public function commonTables(): ?CommonTables;
}
