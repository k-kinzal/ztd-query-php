<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Query\Clause;

use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Node;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * DISTINCT or DISTINCT ON: which rows of a selection are kept when they repeat.
 *
 * Mirrors the `distinctClause` of PostgreSQL's `SelectStmt`: an empty list
 * for DISTINCT, the expressions for DISTINCT ON. The DISTINCT ON
 * expressions are interpreted with the rules of ORDER BY; the selection that
 * holds the clause derives them.
 * Source: https://www.postgresql.org/docs/17/sql-select.html#SQL-DISTINCT.
 *
 * @visibility SqlSemantics
 */
final class DistinctClause implements Node
{
    use Snapshot;

    /**
     * @var list<Scalar> The DISTINCT ON expressions; empty for plain DISTINCT
     */
    public readonly array $on;

    /**
     * @param list<Scalar> $on The DISTINCT ON expressions; empty for plain DISTINCT
     */
    public function __construct(array $on = [])
    {
        $this->on = Check::listOf($on, Scalar::class, 'DISTINCT ON expressions are expressions.');
    }

    /**
     * Writes DISTINCT and the parenthesized DISTINCT ON expressions.
     */
    public function render(Output $out): void
    {
        $out->keyword('DISTINCT');
        if ($this->on !== []) {
            $out->keyword('ON')->symbol('(')->list($this->on)->symbol(')');
        }
    }
}
