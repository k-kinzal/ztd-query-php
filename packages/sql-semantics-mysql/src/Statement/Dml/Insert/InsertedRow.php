<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Insert;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * One parenthesized row of INSERT ... VALUES: values or DEFAULT, one per written column.
 *
 * An empty row with no column list writes the default of every column.
 * The statement that holds the row derives its values.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/insert.html.
 *
 * @visibility public
 * @example Reading the values of a row
 *     $insert = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('INSERT INTO t VALUES (1, 2), ()');
 *     [count($insert->statement->rows[0]->values), count($insert->statement->rows[1]->values)] // => [2, 0]
 */
final class InsertedRow implements Node
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
        $this->values = Check::listOf($values, Scalar::class, 'A row holds values.');
    }

    /**
     * Writes the parenthesized values.
     */
    public function render(Output $out): void
    {
        $out->symbol('(')->list($this->values)->symbol(')');
    }
}
