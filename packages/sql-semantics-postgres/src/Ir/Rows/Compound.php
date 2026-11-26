<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Rows;

use SqlSemantics\Platform\PostgreSql\Ir\Binding\ParameterSignature;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\Values\ValuesQuery;
use SqlSemantics\Platform\PostgreSql\Ir\Select;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A native compound with separate scopes and a fixed weighted row operation.
 *
 * @visibility public
 * @example Preserving repeated rows
 *     $column = new \SqlSemantics\Platform\PostgreSql\Ir\ResultColumn(new \SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral('1'), new \SqlSemantics\Platform\PostgreSql\Ir\Identifier('a'));
 *     $query = new \SqlSemantics\Platform\PostgreSql\Ir\Select([$column]);
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Rows\Compound($query, \SqlSemantics\Platform\PostgreSql\Ir\Rows\CompoundOperator::Union, true, $query))->operator->value // => 'UNION'
 */
final class Compound implements SemanticsIR
{
    use Snapshot;

    /**
     * Closes two query arms over the same output positions.
     */
    public function __construct(
        public readonly Select|ValuesQuery|self $left,
        public readonly CompoundOperator $operator,
        public readonly bool $all,
        public readonly Select|ValuesQuery|self $right,
    ) {
        Check::input($left->profileId() === $right->profileId(), 'Compound operands must have the same language profile.');
        Check::input(CompoundShape::width($left) === CompoundShape::width($right), 'Compound operands must have the same width.');
        Check::input(CompoundShape::arms($left) + CompoundShape::arms($right) <= 64, 'A compound can expand to at most 64 query arms.');
        foreach ([$left, $right] as $operand) {
            Check::input(!$operand instanceof Select || ($operand->orderBy === [] && $operand->limit === null), 'A compound arm requires an unbounded projection without ordering.');
            Check::input(CompoundShape::depth($operand) < 16, 'A compound has at most 16 nested operations.');
        }
        ParameterSignature::check($left, $right);
        for ($index = 0; $index < CompoundShape::width($left); ++$index) {
            CompoundShape::merge(CompoundShape::type($left, $index), CompoundShape::type($right, $index));
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

        return $left . ' ' . $this->operator->value . ($this->all ? ' ALL ' : ' ') . CompoundShape::sql($this->right);
    }
}
