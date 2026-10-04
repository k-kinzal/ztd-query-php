<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Merge;

/**
 * Which rows a WHEN clause of MERGE applies to.
 *
 * Mirrors PostgreSQL's `MergeMatchKind`: `MERGE_WHEN_MATCHED` (a target row
 * joined with a source row), `MERGE_WHEN_NOT_MATCHED_BY_SOURCE` (a target
 * row without source row, PostgreSQL 17) and `MERGE_WHEN_NOT_MATCHED_BY_TARGET`
 * (a source row without target row, written `NOT MATCHED` or, in
 * PostgreSQL 17, `NOT MATCHED BY TARGET`).
 * Source: https://www.postgresql.org/docs/17/sql-merge.html.
 *
 * @visibility public
 * @example Reading the kind of a WHEN clause
 *     $merge = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('MERGE INTO t USING u ON t.a = u.a WHEN NOT MATCHED BY TARGET THEN DO NOTHING');
 *     [$merge->statement->clauses[0]->match, $merge->toString()] // => [\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Merge\MergeMatch::NotMatched, 'MERGE INTO t USING u ON t.a = u.a WHEN NOT MATCHED THEN DO NOTHING']
 */
enum MergeMatch
{
    case Matched;
    case NotMatchedBySource;
    case NotMatched;

    /**
     * Tells whether the clause applies to rows that have a target row: MATCHED and NOT MATCHED BY SOURCE.
     */
    public function targeted(): bool
    {
        return $this !== self::NotMatched;
    }
}
