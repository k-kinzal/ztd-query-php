<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A row of the wrong number of fields or columns where a construct needs a fixed number.
 *
 * Source: https://www.postgresql.org/docs/17/sql-expressions.html#SQL-SYNTAX-SCALAR-SUBQUERIES.
 *
 * @visibility public
 * @example Reading the message
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem\RowArity('an ARRAY subquery', 1, 2))->message() // => 'an ARRAY subquery needs 1 column(s) but has 2.'
 */
final class RowArity implements Diagnostic
{
    use Snapshot;

    /**
     * @param string $construct The construct, such as 'a scalar subquery'
     * @param int $expected The number of fields it needs
     * @param int $actual The number written
     */
    public function __construct(public readonly string $construct, public readonly int $expected, public readonly int $actual)
    {
    }

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        return $this->construct . ' needs ' . $this->expected . ' column(s) but has ' . $this->actual . '.';
    }
}
