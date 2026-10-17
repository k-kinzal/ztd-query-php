<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Snapshot;

/**
 * Parentheses around a row operand, retaining its row-valued role.
 *
 * Grouping never turns a row into a scalar. The enclosed row supplies the
 * comparison fields and their derived facts.
 *
 * @visibility public
 * @example Keeping a grouped row outside the scalar domain
 *     (new \SqlSemantics\Platform\MySql\Statement\Expression\GroupedRow(new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2')])))->operand instanceof \SqlSemantics\Platform\MySql\Statement\Expression\RowOperand // => true
 */
final class GroupedRow implements RowOperand
{
    use Snapshot;

    /**
     * @param RowOperand $operand The grouped row
     */
    public function __construct(public readonly RowOperand $operand)
    {
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Retains the row facts through grouping.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        return $derivation->scalar($this->operand, $environment);
    }

    /**
     * Writes the row in parentheses.
     */
    public function render(Output $out): void
    {
        $out->symbol('(')->node($this->operand)->symbol(')');
    }
}
