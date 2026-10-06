<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Partition;

use SqlSemantics\Platform\PostgreSql\Statement\Clause;

/**
 * The bound of a partition: which rows of the parent it holds.
 *
 * Mirrors PostgreSQL's `PartitionBoundSpec`, one implementation per
 * strategy and one for DEFAULT. The bound values are derived where no column
 * is visible ("cannot use column reference in partition bound expression").
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html.
 *
 * @visibility public
 * @example Reading the kind of a bound
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE TABLE p1 PARTITION OF p FOR VALUES IN (1, 2)');
 *     $create->statement->definition->bound instanceof \SqlSemantics\Platform\PostgreSql\Statement\Table\Partition\ListBound // => true
 */
interface PartitionBound extends Clause
{
}
