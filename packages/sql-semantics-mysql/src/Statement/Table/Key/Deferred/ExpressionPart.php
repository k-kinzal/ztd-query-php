<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Key\Deferred;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Query\Direction;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;
use SqlSemantics\Platform\MySql\Statement\Table\Key\KeyPart;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * An index definition retaining an operand that cannot be an ordinary functional key.
 *
 * Target lookup and index constraints precede operand preparation. In particular,
 * CREATE TABLE IF NOT EXISTS can leave this definition unused. The operand stays
 * distinct from an ordinary functional key. A scalar-only column reference also
 * needs this role: it belongs in a column key rather than a functional key.
 * No source SQL or parser node is retained.
 *
 * @visibility public
 * @example Retaining a row without pretending it is a scalar expression key
 *     $row = new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2')]);
 *     (new \SqlSemantics\Platform\MySql\Statement\Table\Key\Deferred\ExpressionPart($row))->expression === $row // => true
 */
final class ExpressionPart implements KeyPart
{
    use Snapshot;

    /**
     * @param Scalar|RowOperand|DeferredOperand $expression The complete operand awaiting index preparation
     * @param Direction|null $direction The written index order
     */
    public function __construct(public readonly Scalar|RowOperand|DeferredOperand $expression, public readonly ?Direction $direction = null)
    {
        $ungrouped = $expression;
        while ($ungrouped instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Grouped) {
            $ungrouped = $ungrouped->operand;
        }
        \SqlSemantics\Diagnostic\Check::input(!$expression instanceof Scalar || $ungrouped instanceof \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse, 'A deferred scalar key part retains a bare column reference.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Binds dependencies in the table declaration without discarding the index role.
     */
    public function deriveKeyPart(Derivation $derivation, Environment $scope): void
    {
        $derivation->scalar($this->expression, $scope);
    }

    /**
     * Writes the complete parenthesized operand and its ordering.
     */
    public function render(Output $out): void
    {
        $out->symbol('(')->node($this->expression)->symbol(')');
        if ($this->direction !== null) {
            $out->keyword($this->direction->value);
        }
    }
}
