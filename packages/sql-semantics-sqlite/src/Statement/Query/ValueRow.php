<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Query;

use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Statement\Node;
use SqlSemantics\Platform\Sqlite\Model\Statement\Scalar;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * One row of a VALUES clause.
 *
 * @visibility SqlSemantics
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
