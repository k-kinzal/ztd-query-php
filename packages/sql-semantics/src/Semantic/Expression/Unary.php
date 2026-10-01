<?php

declare(strict_types=1);

namespace SqlSemantics\Semantic\Expression;

use SqlSemantics\Core\Type\Nullability;
use SqlSemantics\Core\Type\TypeDescriptor;
use SqlSemantics\Semantic\Scope;
use SqlSemantics\Semantic\Type\InvalidReference;
use SqlSemantics\Semantic\Type\Undetermined;

/**
 * An operation on one operand.
 * @example Reading semantic relationships
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT NULL IS NULL AS test');
 *     $statement->field('test')->expression->operator->value // => 'IS NULL'
 *
 * @visibility public
 */
final class Unary
{
    /**
     * The result type or the explicit reason no type can be established.
     */
    public readonly TypeDescriptor|Undetermined|InvalidReference $type;
    /**
     * The conservative NULL fact at this evaluation stage.
     */
    public readonly Nullability $nullability;

    /**
     * Constructs the value and asserts the relationships required by its fields.
     */
    public function __construct(
        public readonly Scope $scope,
        public readonly UnaryOperator $operator,
        public readonly ColumnReference|Literal|Parameter|Binary|self|Coalesce|NullIf $operand,
    ) {
        [$this->type, $this->nullability] = Operands::facts($scope, $operator->value, [$operand]);
    }

    /**
     * Reconstructs SQL from the semantic values without consulting source syntax.
     */
    public function toString(): string
    {
        $operand = $this->operand instanceof Binary || $this->operand instanceof self ? '(' . $this->operand->toString() . ')' : $this->operand->toString();
        if (in_array($this->operator, [UnaryOperator::IsNull, UnaryOperator::IsNotNull], true)) {
            return $operand . ' ' . $this->operator->value;
        }
        return $this->operator->value . ' ' . $operand;
    }
}
