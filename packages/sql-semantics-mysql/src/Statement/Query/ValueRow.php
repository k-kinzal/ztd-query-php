<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * One row constructor of a VALUES statement: `ROW(value, ...)`.
 *
 * The VALUES statement that holds the row derives its values.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/values.html.
 *
 * @visibility public
 * @example Reading the values of a row
 *     $row = new \SqlSemantics\Platform\MySql\Statement\Query\ValueRow([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2')]);
 *     count($row->values) // => 2
 */
final class ValueRow implements Node
{
    use Snapshot;

    /**
     * @var list<Scalar> The values in written order
     */
    public readonly array $values;

    /**
     * @param list<Scalar> $values The values in written order
     */
    public function __construct(array $values)
    {
        $this->values = Check::listOf($values, Scalar::class, 'A row constructor holds values.');
    }

    /**
     * Writes the row constructor.
     */
    public function render(Output $out): void
    {
        $out->keyword('ROW')->glue()->symbol('(')->list($this->values)->symbol(')');
    }
}
