<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Problem;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * An operand with a number of columns its position does not take (`ER_OPERAND_COLUMNS`, error 1241).
 *
 * A single value is one column; a row constructor or a subquery of several
 * columns is a row. The server rejects a row where a single value is
 * required and rows of different widths in one comparison.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/row-subqueries.html,
 * https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html#error_er_operand_columns.
 *
 * @visibility public
 * @example Reporting a row where a single value is required
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t WHERE (a, b) + 1');
 *     $query->facts->diagnostics[0]->message() // => 'Operand should contain 1 column(s), not 2.'
 */
final class OperandColumns implements Diagnostic
{
    use Snapshot;

    /**
     * @param int $expected The number of columns the position takes
     * @param int $actual The number of columns the operand has
     */
    public function __construct(public readonly int $expected, public readonly int $actual)
    {
        Check::input($expected >= 1 && $actual >= 1 && $expected !== $actual, 'An operand column problem names two different column counts.');
    }

    /**
     * Describes the problem as the server does, with the actual width.
     */
    public function message(): string
    {
        return 'Operand should contain ' . $this->expected . ' column(s), not ' . $this->actual . '.';
    }
}
