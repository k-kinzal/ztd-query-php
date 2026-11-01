<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\With\DeferredBody;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A complete unparenthesized operand whose trailing clause cannot precede UNION.
 *
 * The input retains its operand before a set expression is constructed. The
 * legacy grammar determines whether an intervening LIMIT has this role.
 *
 * @visibility public
 * @example Retaining a misplaced ordering
 *     $select = new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'))], orderBy: [new \SqlSemantics\Platform\MySql\Statement\Query\OrderItem(new \SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1')))]);
 *     (new \SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\RefusedSetClause($select))->message() // => 'Incorrect usage of UNION and ORDER BY'
 */
final class RefusedSetClause implements Diagnostic
{
    use Snapshot;

    /**
     * @param Select|DeferredBody $operand The complete leading operand
     */
    public function __construct(public readonly Select|DeferredBody $operand)
    {
        $block = $operand instanceof DeferredBody ? $operand->selection : $operand;
        Check::input($block->orderBy !== [] || $block->limit !== null || $block->procedure !== null, 'A refused set clause retains an ordering, limit or result analysis.');
    }

    /**
     * Describes the clause that cannot precede the set operator.
     */
    public function message(): string
    {
        $block = $this->operand instanceof DeferredBody ? $this->operand->selection : $this->operand;
        return 'Incorrect usage of UNION and ' . ($block->orderBy !== [] ? 'ORDER BY' : ($block->limit !== null ? 'LIMIT' : 'SELECT ... PROCEDURE ANALYSE()'));
    }
}
