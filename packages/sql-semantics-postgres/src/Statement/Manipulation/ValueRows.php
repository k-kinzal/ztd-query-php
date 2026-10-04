<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\ValuesRow;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * The VALUES rows of an INSERT: rows whose values are assigned to the target columns one by one.
 *
 * PostgreSQL treats a VALUES list that is the whole source of an INSERT
 * apart from a query: each value is converted to the type of the column it
 * is assigned to, and a value may be DEFAULT.
 * Source: https://www.postgresql.org/docs/17/sql-insert.html, https://www.postgresql.org/docs/17/sql-values.html.
 *
 * @visibility public
 * @example Reading the rows of an INSERT
 *     $insert = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('INSERT INTO t VALUES (1), (DEFAULT)');
 *     count($insert->statement->rows->rows()) // => 2
 * @example Refusing VALUES without a row
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\ValueRows([]) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class ValueRows implements Node
{
    use Snapshot;

    /**
     * @var non-empty-list<ValuesRow> The rows in written order
     */
    public readonly array $rows;

    /**
     * @param list<ValuesRow> $rows The rows in written order; at least one
     *
     * @throws InvalidConstruction When there is no row
     */
    public function __construct(array $rows)
    {
        $this->rows = Check::listOf($rows, ValuesRow::class, 'VALUES holds at least one row.', 1);
    }

    /**
     * Answers the rows.
     *
     * @return non-empty-list<ValuesRow>
     */
    public function rows(): array
    {
        return $this->rows;
    }

    /**
     * Writes VALUES and the rows.
     */
    public function render(Output $out): void
    {
        $out->keyword('VALUES')->list($this->rows);
    }
}
