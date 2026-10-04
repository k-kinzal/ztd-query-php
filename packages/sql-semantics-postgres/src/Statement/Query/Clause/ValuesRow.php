<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Query\Clause;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * One parenthesized row of a VALUES list.
 *
 * Source: https://www.postgresql.org/docs/17/sql-values.html.
 *
 * @visibility public
 * @example Reading the values of a row
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('VALUES (1, 2)');
 *     count($query->statement->rows[0]->values) // => 2
 * @example Refusing an empty row
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\ValuesRow([]) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class ValuesRow implements Node
{
    use Snapshot;

    /**
     * @var non-empty-list<Scalar> The values in column order
     */
    public readonly array $values;

    /**
     * @param list<Scalar> $values The values in column order; at least one
     */
    public function __construct(array $values)
    {
        $this->values = Check::listOf($values, Scalar::class, 'A VALUES row holds at least one expression.', 1);
    }

    /**
     * Writes the values in parentheses.
     */
    public function render(Output $out): void
    {
        $out->symbol('(')->list($this->values)->symbol(')');
    }
}
