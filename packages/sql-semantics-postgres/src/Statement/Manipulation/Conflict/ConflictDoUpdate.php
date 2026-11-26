<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Conflict;

use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Node;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Rules\Query\ClosedList;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Assignment\Assignment;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Assignment\RowAssignment;

/**
 * `ON CONFLICT [ conflict_target ] DO UPDATE SET ... [ WHERE condition ]`: the existing row that conflicts is updated instead.
 *
 * Mirrors PostgreSQL's `OnConflictClause` with `ONCONFLICT_UPDATE`. The
 * assignments and the condition see the target table and, under the name
 * `excluded`, the row proposed for insertion. PostgreSQL requires a
 * conflict target; its absence is a diagnostic.
 * Source: https://www.postgresql.org/docs/17/sql-insert.html#SQL-ON-CONFLICT.
 *
 * @visibility SqlSemantics
 * @example Refusing DO UPDATE without an assignment
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Conflict\ConflictDoUpdate(null, []) // throws \SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction
 */
final class ConflictDoUpdate implements Node
{
    use Snapshot;

    /**
     * @var non-empty-list<Assignment|RowAssignment> The SET items in written order
     */
    public readonly array $assignments;

    /**
     * @param IndexInference|ConstraintInference|null $target The conflict target
     * @param list<Assignment|RowAssignment> $assignments The SET items in written order; at least one
     * @param Scalar|null $where The condition a conflicting row must meet to be updated
     *
     * @throws InvalidConstruction When there is no SET item
     */
    public function __construct(public readonly IndexInference|ConstraintInference|null $target, array $assignments, public readonly ?Scalar $where = null)
    {
        $this->assignments = (new ClosedList())->of($assignments, [Assignment::class, RowAssignment::class], 'DO UPDATE SET holds at least one assignment.', 1);
    }

    /**
     * Writes ON CONFLICT, the target, DO UPDATE SET, the assignments and the condition.
     */
    public function render(Output $out): void
    {
        $out->keyword('ON', 'CONFLICT')->node($this->target)->keyword('DO', 'UPDATE', 'SET')->list($this->assignments);
        if ($this->where !== null) {
            $out->keyword('WHERE')->node($this->where);
        }
    }
}
