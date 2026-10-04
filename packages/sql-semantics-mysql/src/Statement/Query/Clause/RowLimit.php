<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query\Clause;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberForm;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter;
use SqlSemantics\Platform\MySql\Statement\Query\Limit;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * A LIMIT clause: a row count and an optional offset.
 *
 * Each operand is an unsigned integer literal, a parameter marker or a
 * stored program variable; MySQL accepts no other expression there. The
 * spelling of an offset is kept because `LIMIT offset, count` and
 * `LIMIT count OFFSET offset` write the operands in opposite order.
 * The statement that holds the clause derives its operands, which see no
 * column. Source: https://dev.mysql.com/doc/refman/8.4/en/select.html.
 *
 * @visibility public
 * @example Reading the row count and the offset
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t LIMIT 10 OFFSET 5');
 *     [$query->statement->limit->count->text, $query->statement->limit->offset->text] // => ['10', '5']
 * @example Refusing an expression as a row count
 *     new \SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1.5')) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class RowLimit implements Limit
{
    use Snapshot;

    /**
     * @param Scalar $count The row count
     * @param Scalar|null $offset The number of rows skipped
     * @param OffsetSpelling|null $spelling How the offset is written; given exactly when there is an offset
     */
    public function __construct(public readonly Scalar $count, public readonly ?Scalar $offset = null, public readonly ?OffsetSpelling $spelling = null)
    {
        foreach ([$count, $offset] as $operand) {
            Check::input($operand === null || $operand instanceof Parameter || $operand instanceof ProgramVariable || ($operand instanceof NumberLiteral && $operand->form === NumberForm::Integer), 'A LIMIT operand is an unsigned integer, a parameter marker or a stored program variable.');
        }
        Check::input(($offset === null) === ($spelling === null), 'An offset is written in exactly one spelling.');
    }

    /**
     * Writes the clause.
     */
    public function render(Output $out): void
    {
        $out->keyword('LIMIT');
        if ($this->spelling === OffsetSpelling::Comma) {
            $out->node($this->offset)->symbol(',');
        }
        $out->node($this->count);
        if ($this->spelling === OffsetSpelling::Keyword) {
            $out->keyword('OFFSET')->node($this->offset);
        }
    }
}
