<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Query\Limit;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * The LIMIT, FETCH FIRST and OFFSET clauses of a query, in the order written.
 *
 * Mirrors `limitCount`, `limitOffset` and `limitOption` of PostgreSQL's
 * `SelectStmt`. The count and the offset may be written in either order;
 * the order is kept.
 * Source: https://www.postgresql.org/docs/17/sql-select.html#SQL-LIMIT.
 *
 * @visibility public
 * @example Keeping an offset written before the limit
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT 1 OFFSET 1 LIMIT 2');
 *     [$query->statement->options->limit->offsetFirst, $query->toString()] // => [true, 'SELECT 1 OFFSET 1 LIMIT 2']
 * @example Refusing an empty limit
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Query\Limit\RowLimit() // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class RowLimit implements Node
{
    use Snapshot;

    /**
     * @param LimitCount|CommaLimit|FetchFirst|null $count The row count clause
     * @param Offset|null $offset The offset clause
     * @param bool $offsetFirst Whether the offset is written before the count
     */
    public function __construct(
        public readonly LimitCount|CommaLimit|FetchFirst|null $count = null,
        public readonly ?Offset $offset = null,
        public readonly bool $offsetFirst = false,
    ) {
        Check::input($count !== null || $offset !== null, 'A row limit has a count or an offset.');
        Check::input(!$offsetFirst || ($count !== null && $offset !== null), 'Only an offset written with a count can come first.');
    }

    /**
     * Writes the count and the offset in the order written.
     */
    public function render(Output $out): void
    {
        if ($this->offsetFirst) {
            $out->node($this->offset)->node($this->count);

            return;
        }
        $out->node($this->count)->node($this->offset);
    }
}
