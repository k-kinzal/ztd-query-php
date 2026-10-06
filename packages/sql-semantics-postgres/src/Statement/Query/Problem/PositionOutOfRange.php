<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Query\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * An output column position that the select list does not have.
 *
 * @visibility public
 * @example Reporting a position past the select list
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT 1 ORDER BY 2');
 *     $query->facts->diagnostics[0]->message() // => 'ORDER BY position 2 is not in select list'
 */
final class PositionOutOfRange implements Diagnostic
{
    use Snapshot;

    /**
     * @param OrderingClause $clause The clause the position is written in
     * @param string $position The position as an exact decimal integer
     */
    public function __construct(public readonly OrderingClause $clause, public readonly string $position)
    {
    }

    /**
     * Describes the problem in the words of PostgreSQL.
     */
    public function message(): string
    {
        return $this->clause->value . ' position ' . $this->position . ' is not in select list';
    }
}
