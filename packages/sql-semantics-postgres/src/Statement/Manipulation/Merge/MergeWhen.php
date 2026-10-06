<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Merge;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * One WHEN clause of MERGE: the rows it applies to, an optional condition and the action.
 *
 * Mirrors PostgreSQL's `MergeWhenClause`. A clause for rows with a target
 * row updates, deletes or skips; a clause for source rows without target
 * row inserts or skips.
 * Source: https://www.postgresql.org/docs/17/sql-merge.html.
 *
 * @visibility public
 * @example Reading the condition of a WHEN clause
 *     $merge = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('MERGE INTO t USING u ON t.a = u.a WHEN MATCHED AND u.c THEN DELETE');
 *     $merge->statement->clauses[0]->condition !== null // => true
 * @example Refusing an insert for rows that have a target row
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Merge\MergeWhen(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Merge\MergeMatch::Matched, null, new \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Merge\MergeInsertDefaults()) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class MergeWhen implements Node
{
    use Snapshot;

    /**
     * @param MergeMatch $match The rows the clause applies to
     * @param Scalar|null $condition The condition written after AND
     * @param MergeUpdate|MergeDelete|MergeInsert|MergeInsertDefaults|MergeNothing $action The action
     *
     * @throws InvalidConstruction When the action does not fit the rows
     */
    public function __construct(
        public readonly MergeMatch $match,
        public readonly ?Scalar $condition,
        public readonly MergeUpdate|MergeDelete|MergeInsert|MergeInsertDefaults|MergeNothing $action,
    ) {
        $inserts = $action instanceof MergeInsert || $action instanceof MergeInsertDefaults;
        $changes = $action instanceof MergeUpdate || $action instanceof MergeDelete;
        Check::input($match->targeted() ? !$inserts : !$changes, 'A clause for target rows updates, deletes or skips; a clause for source rows inserts or skips.');
    }

    /**
     * Writes WHEN, the rows, the condition, THEN and the action.
     */
    public function render(Output $out): void
    {
        $out->keyword(...match ($this->match) {
            MergeMatch::Matched => ['WHEN', 'MATCHED'],
            MergeMatch::NotMatchedBySource => ['WHEN', 'NOT', 'MATCHED', 'BY', 'SOURCE'],
            MergeMatch::NotMatched => ['WHEN', 'NOT', 'MATCHED'],
        });
        if ($this->condition !== null) {
            $out->keyword('AND')->node($this->condition);
        }
        $out->keyword('THEN')->node($this->action);
    }
}
