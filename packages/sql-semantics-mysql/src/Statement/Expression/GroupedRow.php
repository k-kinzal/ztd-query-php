<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Snapshot;

/**
 * Grouping around a row operand, retaining its row-valued role.
 *
 * Parentheses and ODBC braces both preserve the row. An optional escape kind
 * distinguishes braces without converting the row into a scalar.
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
     * @param \SqlSemantics\Statement\Identifier\Name|null $escape An ODBC escape kind, or null for ordinary parentheses
     */
    public function __construct(public readonly RowOperand $operand, public readonly ?\SqlSemantics\Statement\Identifier\Name $escape = null)
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
        if ($this->escape !== null) {
            $out->symbol('{')->name($this->escape, \SqlSemantics\Contract\NameUse::Label)->node($this->operand)->symbol('}');
            return;
        }
        $out->symbol('(')->node($this->operand)->symbol(')');
    }
}
