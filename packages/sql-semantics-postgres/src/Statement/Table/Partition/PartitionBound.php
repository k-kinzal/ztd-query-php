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
 * @visibility SqlSemantics
 */
interface PartitionBound extends Clause
{
}
