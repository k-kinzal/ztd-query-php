<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand;

use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A standalone selection requiring operands outside the scalar query domain.
 *
 * The complete semantic selection is retained for dependency resolution before
 * reporting its operand condition. It is a refusal, never an ordinary Statement.
 *
 * @visibility public
 * @example Keeping a refused selection outside the statement domain
 *     $selection = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection([new \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredProjection(new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2')]))]);
 *     (new \SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\RefusedSelection($selection)) instanceof \SqlSemantics\Statement\Statement // => false
 */
final class RefusedSelection implements Diagnostic
{
    use Snapshot;

    /**
     * @param DeferredSelection $selection The complete semantic preparation dependencies
     */
    public function __construct(public readonly DeferredSelection $selection)
    {
    }

    /**
     * Describes the scalar-use refusal without reconstructing input SQL.
     */
    public function message(): string
    {
        return $this->problem()->message();
    }
    /**
     * Describes the first written operand constraint; dependency errors may precede it.
     */
    public function problem(): Diagnostic
    {
        $operand = $this->selection->where;
        foreach ($this->selection->items as $item) {
            if ($item instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredProjection) {
                $operand = $item->expression;
                break;
            }
        }
        \SqlSemantics\Diagnostic\Check::invariant($operand instanceof \SqlSemantics\Platform\MySql\Statement\Expression\RowOperand || $operand instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand, 'A deferred selection contains a deferred projection or predicate.');
        return $operand instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand ? $operand->problem() : new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns(1, count((new \SqlSemantics\Platform\MySql\Rules\Expression\Shape\RowShapes())->parts($operand) ?? []));
    }
}
