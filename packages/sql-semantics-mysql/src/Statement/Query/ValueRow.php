<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Dml\DefaultRequest;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Operand;
use SqlSemantics\Statement\Scalar;

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
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @var list<Scalar|DefaultRequest> The values in written order
     */
    public readonly array $values;

    /**
     * @param list<Scalar|DefaultRequest> $values The values in written order
     */
    public function __construct(array $values, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        $accepted = [];
        foreach (Check::listOf($values, Operand::class, 'A row holds values.') as $value) {
            Check::input($value instanceof Scalar || $value instanceof DefaultRequest, 'A row value is an expression or DEFAULT.');
            $accepted[] = $value;
        }
        $this->values = $accepted;
    }

    /**
     * Writes the row constructor.
     */
    public function render(Output $out): void
    {
        $out->keyword('ROW')->glue()->symbol('(')->list($this->values)->symbol(')');
    }
}
