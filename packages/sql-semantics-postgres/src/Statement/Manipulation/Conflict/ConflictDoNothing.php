<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Conflict;

use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * `ON CONFLICT [ conflict_target ] DO NOTHING`: a row that would violate a unique constraint is not inserted.
 *
 * Mirrors PostgreSQL's `OnConflictClause` with `ONCONFLICT_NOTHING`.
 * Without a target, a conflict with any unique constraint is skipped.
 * Source: https://www.postgresql.org/docs/17/sql-insert.html#SQL-ON-CONFLICT.
 *
 * @visibility public
 * @example Reading a conflict clause without target
 *     $insert = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('INSERT INTO t VALUES (1) ON CONFLICT DO NOTHING');
 *     $insert->statement->conflict->target // => null
 */
final class ConflictDoNothing implements Node
{
    use Snapshot;

    /**
     * @param IndexInference|ConstraintInference|null $target The conflict target
     */
    public function __construct(public readonly IndexInference|ConstraintInference|null $target = null)
    {
    }

    /**
     * Writes ON CONFLICT, the target and DO NOTHING.
     */
    public function render(Output $out): void
    {
        $out->keyword('ON', 'CONFLICT')->node($this->target)->keyword('DO', 'NOTHING');
    }
}
