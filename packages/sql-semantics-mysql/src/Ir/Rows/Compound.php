<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Rows;

use SqlSemantics\Platform\MySql\Ir\From\IntegralColumnReference;
use SqlSemantics\Platform\MySql\Ir\Select;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A native compound with separate scopes and a fixed weighted row operation.
 *
 * @visibility public
 * @example Preserving repeated rows
 *     $column = new \SqlSemantics\Platform\MySql\Ir\ResultColumn(new \SqlSemantics\Platform\MySql\Ir\IntegerLiteral('1'), new \SqlSemantics\Platform\MySql\Ir\Identifier('a'));
 *     $query = new \SqlSemantics\Platform\MySql\Ir\Select([$column]);
 *     (new \SqlSemantics\Platform\MySql\Ir\Rows\Compound($query, \SqlSemantics\Platform\MySql\Ir\Rows\CompoundOperator::Union, true, $query))->operator->value // => 'UNION'
 */
final class Compound implements SemanticsIR
{
    use Snapshot;

    /**
     * Closes two query arms over the same output positions.
     */
    public function __construct(
        public readonly Select|self $left,
        public readonly CompoundOperator $operator,
        public readonly bool $all,
        public readonly Select|self $right,
    ) {
        Check::input($left->profileId() === $right->profileId(), 'Compound operands must have the same language profile.');
        Check::input(CompoundShape::width($left) <= 1024, 'A compound can have at most 1024 result columns.');
        Check::input(CompoundShape::width($left) === CompoundShape::width($right), 'Compound operands must have the same width.');
        Check::input(CompoundShape::arms($left) + CompoundShape::arms($right) <= 64, 'A compound can expand to at most 64 query arms.');
        foreach ([$left, $right] as $operand) {
            Check::input(!$operand instanceof Select || ($operand->orderBy === [] && $operand->limit === null), 'A compound arm requires an unbounded projection without ordering.');
            Check::input(CompoundShape::depth($operand) < 16, 'A compound has at most 16 nested operations.');
            if ($operand instanceof Select) {
                foreach ($operand->columns as $column) {
                    Check::input(!$column->expression instanceof IntegralColumnReference, 'A compound arm requires a supported native set column type.');
                }
            }
        }
        if (CompoundShape::legacy($left)) {
            Check::input($operator === CompoundOperator::Union && $right instanceof Select, 'MySQL 5.x compounds require a left-associated UNION.');
        }
    }

    /**
     * Identifies the shared release and lexical profile of the arms.
     */
    public function profileId(): string
    {
        return $this->left->profileId();
    }

    /**
     * Emits the checked operation and the syntax required for its grouping.
     */
    public function toString(): string
    {
        $left = CompoundShape::sql($this->left);
        if (CompoundShape::legacy($this->left)) {
            $left = $this->left->toString();
        }

        return $left . ' ' . $this->operator->value . ($this->all ? ' ALL ' : ' ') . CompoundShape::sql($this->right);
    }
}
