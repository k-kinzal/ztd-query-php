<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Merge;

use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Rules\Query\ClosedList;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Assignment\Assignment;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Assignment\RowAssignment;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * The action UPDATE SET of a MERGE WHEN clause: the target row is updated.
 *
 * Mirrors a `MergeWhenClause` with `CMD_UPDATE` and its target list.
 * Source: https://www.postgresql.org/docs/17/sql-merge.html.
 *
 * @visibility public
 * @example Reading an update action
 *     $merge = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('MERGE INTO t USING u ON t.a = u.a WHEN MATCHED THEN UPDATE SET b = DEFAULT');
 *     count($merge->statement->clauses[0]->action->assignments) // => 1
 * @example Refusing an update without assignment
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Merge\MergeUpdate([]) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class MergeUpdate implements Node
{
    use Snapshot;

    /**
     * @var non-empty-list<Assignment|RowAssignment> The SET items in written order
     */
    public readonly array $assignments;

    /**
     * @param list<Assignment|RowAssignment> $assignments The SET items in written order; at least one
     *
     * @throws InvalidConstruction When there is no SET item
     */
    public function __construct(array $assignments)
    {
        $this->assignments = (new ClosedList())->of($assignments, [Assignment::class, RowAssignment::class], 'UPDATE SET holds at least one assignment.', 1);
    }

    /**
     * Writes UPDATE SET and the assignments.
     */
    public function render(Output $out): void
    {
        $out->keyword('UPDATE', 'SET')->list($this->assignments);
    }
}
