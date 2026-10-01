<?php

declare(strict_types=1);

namespace SqlSemantics\Semantic\Expression;

use SqlSemantics\Core\Type\Nullability;
use SqlSemantics\Core\Type\TypeDescriptor;
use SqlSemantics\Semantic\Scope;
use SqlSemantics\Semantic\Type\InvalidReference;
use SqlSemantics\Semantic\Type\Undetermined;

/**
 * An operation on two ordered operands with derived facts.
 * @example Reading semantic relationships
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT 2 + 3 AS sum');
 *     $statement->field('sum')->expression->operator->value // => '+'
 *
 * @visibility public
 */
final class Binary
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
        public readonly BinaryOperator $operator,
        public readonly ColumnReference|Literal|Parameter|self|Unary|Coalesce|NullIf $left,
        public readonly ColumnReference|Literal|Parameter|self|Unary|Coalesce|NullIf $right,
    ) {
        [$this->type, $this->nullability] = Operands::facts($scope, $operator->value, [$left, $right]);
    }

    /**
     * Reconstructs SQL from the semantic values without consulting source syntax.
     */
    public function toString(): string
    {
        $left = $this->left instanceof self || $this->left instanceof Unary ? '(' . $this->left->toString() . ')' : $this->left->toString();
        $right = $this->right instanceof self || $this->right instanceof Unary ? '(' . $this->right->toString() . ')' : $this->right->toString();
        return $left . ' ' . $this->operator->value . ' ' . $right;
    }
}
