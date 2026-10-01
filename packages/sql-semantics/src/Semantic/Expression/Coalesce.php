<?php

declare(strict_types=1);

namespace SqlSemantics\Semantic\Expression;

use SqlSemantics\Core\Type\Nullability;
use SqlSemantics\Core\Type\TypeDescriptor;
use SqlSemantics\Semantic\Scope;
use SqlSemantics\Semantic\Type\InvalidReference;
use SqlSemantics\Semantic\Type\Undetermined;

/**
 * The first non-NULL input, retaining operand order.
 * @example Reading semantic relationships
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT COALESCE(NULL, 1) AS value');
 *     $statement->field('value')->expression->nullability->value // => 'not-null'
 *
 * @visibility public
 */
final class Coalesce
{
    /**
     * @var non-empty-list<ColumnReference|Literal|Parameter|Binary|Unary|self|NullIf>
     */
    public readonly array $operands;
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
    public function __construct(public readonly Scope $scope, ColumnReference|Literal|Parameter|Binary|Unary|self|NullIf $first, ColumnReference|Literal|Parameter|Binary|Unary|self|NullIf ...$rest)
    {
        $this->operands = [$first, ...array_values($rest)];
        [$this->type, $this->nullability] = Operands::facts($scope, 'COALESCE', $this->operands, true);
    }

    /**
     * Reconstructs SQL from the semantic values without consulting source syntax.
     */
    public function toString(): string
    {
        return 'COALESCE(' . implode(', ', array_map(static fn ($operand): string => $operand->toString(), $this->operands)) . ')';
    }
}
