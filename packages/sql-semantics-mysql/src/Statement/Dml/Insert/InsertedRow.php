<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Insert;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Dml\DefaultRequest;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Operand;
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
 *     $insert = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('INSERT INTO t VALUES (1, 2), (3, DEFAULT)');
 *     [count($insert->statement->rows[0]->values), count($insert->statement->rows[1]->values)] // => [2, 2]
 */
final class InsertedRow implements Node
{
    use Snapshot;

    /**
     * @var list<Scalar|DefaultRequest> The values in written order
     */
    public readonly array $values;

    /**
     * @param list<Scalar|DefaultRequest> $values The values in written order
     */
    public function __construct(array $values)
    {
        $accepted = [];
        foreach (Check::listOf($values, Operand::class, 'A row holds values.') as $value) {
            Check::input($value instanceof Scalar || $value instanceof DefaultRequest, 'A row value is an expression or DEFAULT.');
            Check::input((new \SqlSemantics\Platform\MySql\Rules\Dml\Insert\ValueGroups())->problem($value) === null, 'A VALUES element has no ambient grouping or window role.');
            $accepted[] = $value;
        }
        $this->values = $accepted;
    }

    /**
     * Writes the parenthesized values.
     */
    public function render(Output $out): void
    {
        $out->symbol('(')->list($this->values)->symbol(')');
    }
}
