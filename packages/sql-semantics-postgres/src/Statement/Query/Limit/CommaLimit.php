<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Query\Limit;

use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;

/**
 * LIMIT count, offset: a spelling the grammar accepts and PostgreSQL rejects.
 *
 * The server asks for separate LIMIT and OFFSET clauses instead; the
 * structure keeps both operands and the query reports the problem.
 * Source: https://www.postgresql.org/docs/17/sql-select.html#SQL-LIMIT.
 *
 * @visibility public
 * @example Reporting LIMIT with a comma
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT 1 LIMIT 1, 2');
 *     $query->diagnostics()[0]->message() // => 'LIMIT #,# syntax is not supported'
 */
final class CommaLimit implements Node
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param Scalar|null $count The row count; null for ALL
     * @param Scalar $offset The number of rows to skip
     */
    public function __construct(public readonly ?Scalar $count, public readonly Scalar $offset, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
    }

    /**
     * Writes LIMIT, the count or ALL, a comma and the offset.
     */
    public function render(Output $out): void
    {
        $out->keyword('LIMIT');
        if ($this->count === null) {
            $out->keyword('ALL');
        } else {
            $out->node($this->count);
        }
        $out->symbol(',')->node($this->offset);
    }
}
