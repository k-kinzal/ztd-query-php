<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Query\Clause;

use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Node;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * One parenthesized row of a VALUES list.
 *
 * Source: https://www.postgresql.org/docs/17/sql-values.html.
 *
 * @visibility SqlSemantics
 * @example Refusing an empty row
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\ValuesRow([]) // throws \SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction
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
