<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Query\Problem;

use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * An output column position that the select list does not have.
 *
 * @visibility SqlSemantics
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
