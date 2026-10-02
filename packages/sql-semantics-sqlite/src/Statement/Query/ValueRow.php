<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Query;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * One row of a VALUES clause.
 *
 * @visibility public
 * @example Reading a row of values
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze("VALUES (1, 'a')");
 *     $query->statement->rows[0]->values[1]->value // => 'a'
 */
final class ValueRow implements Node
{
    use Snapshot;

    /**
     * @var non-empty-list<Scalar> The values in order
     */
    public readonly array $values;

    /**
     * @param list<Scalar> $values The values in order; at least one
     */
    public function __construct(array $values)
    {
        $this->values = Check::listOf($values, Scalar::class, 'A row of values has at least one expression.', 1);
    }

    /**
     * Writes the values in parentheses.
     */
    public function render(Output $out): void
    {
        $out->symbol('(')->list($this->values)->symbol(')');
    }
}
