<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Conflict;

use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Node;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * `ON CONFLICT [ conflict_target ] DO NOTHING`: a row that would violate a unique constraint is not inserted.
 *
 * Mirrors PostgreSQL's `OnConflictClause` with `ONCONFLICT_NOTHING`.
 * Without a target, a conflict with any unique constraint is skipped.
 * Source: https://www.postgresql.org/docs/17/sql-insert.html#SQL-ON-CONFLICT.
 *
 * @visibility SqlSemantics
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
