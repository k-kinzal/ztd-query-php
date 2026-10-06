<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation;

use SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\ValuesRow;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * The VALUES rows of an INSERT written in parentheses that group nothing.
 *
 * The grammar keeps no node for parentheses around a query, so
 * `INSERT INTO t (VALUES (1))` requests the same as `INSERT INTO t VALUES (1)`.
 * Source: https://www.postgresql.org/docs/17/sql-insert.html.
 *
 * @visibility public
 * @example Keeping the parentheses around VALUES
 *     $insert = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('INSERT INTO t (VALUES (DEFAULT))');
 *     [count($insert->statement->rows->rows()), $insert->toString()] // => [1, 'INSERT INTO t (VALUES (DEFAULT))']
 */
final class ParenthesizedRows implements Node
{
    use Snapshot;

    /**
     * @param ValueRows|ParenthesizedRows $rows The rows inside the parentheses
     */
    public function __construct(public readonly ValueRows|ParenthesizedRows $rows)
    {
    }

    /**
     * Answers the rows inside every pair of parentheses.
     *
     * @return non-empty-list<ValuesRow>
     */
    public function rows(): array
    {
        return $this->rows->rows();
    }

    /**
     * Writes the rows in parentheses.
     */
    public function render(Output $out): void
    {
        $out->symbol('(')->node($this->rows)->symbol(')');
    }
}
