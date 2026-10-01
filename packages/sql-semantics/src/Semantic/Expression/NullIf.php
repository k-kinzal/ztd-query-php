<?php

declare(strict_types=1);

namespace SqlSemantics\Semantic\Expression;

use SqlSemantics\Core\Type\Nullability;
use SqlSemantics\Core\Type\TypeDescriptor;
use SqlSemantics\Semantic\Scope;
use SqlSemantics\Semantic\Type\InvalidReference;
use SqlSemantics\Semantic\Type\Undetermined;

/**
 * The first input, made NULL when the two inputs compare equal.
 * @example Reading semantic relationships
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT NULLIF(1, 1) AS value');
 *     $statement->field('value')->expression->nullability->value // => 'maybe-null'
 *
 * @visibility public
 */
final class NullIf
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
    public function __construct(public readonly Scope $scope, public readonly ColumnReference|Literal|Parameter|Binary|Unary|Coalesce|self $left, public readonly ColumnReference|Literal|Parameter|Binary|Unary|Coalesce|self $right)
    {
        [$this->type, $this->nullability] = Operands::facts($scope, 'NULLIF', [$left, $right], true);
    }

    /**
     * Reconstructs SQL from the semantic values without consulting source syntax.
     */
    public function toString(): string
    {
        return 'NULLIF(' . $this->left->toString() . ', ' . $this->right->toString() . ')';
    }
}
