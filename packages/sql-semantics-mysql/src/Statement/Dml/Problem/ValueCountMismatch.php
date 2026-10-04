<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Problem;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A row of an INSERT or REPLACE with another number of values than the columns it writes.
 *
 * The row number counts from 1 for a row of VALUES; a query source has no row number.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/insert.html,
 * https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html (ER_WRONG_VALUE_COUNT_ON_ROW,
 * ER_WRONG_VALUE_COUNT).
 *
 * @visibility public
 * @example Reading a row with too many values
 *     $insert = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('INSERT INTO t (a) VALUES (1), (2, 3)');
 *     [$insert->facts->diagnostics[0]->expected, $insert->facts->diagnostics[0]->actual, $insert->facts->diagnostics[0]->message()] // => [1, 2, "Column count doesn't match value count at row 2"]
 */
final class ValueCountMismatch implements Diagnostic
{
    use Snapshot;

    /**
     * @param int $expected The number of columns written
     * @param int $actual The number of values of the row
     * @param int|null $row The row number counted from 1, or null for the rows of a query
     */
    public function __construct(public readonly int $expected, public readonly int $actual, public readonly ?int $row = null)
    {
        Check::input($row === null || $row > 0, 'Rows are counted from 1.');
    }

    /**
     * Describes the problem in the words of the server.
     */
    public function message(): string
    {
        return $this->row === null ? "Column count doesn't match value count" : "Column count doesn't match value count at row " . $this->row;
    }
}
