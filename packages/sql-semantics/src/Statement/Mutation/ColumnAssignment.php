<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Mutation;

use SqlSemantics\Statement\Expression\ColumnReference;
use SqlSemantics\Statement\Expression\ScalarExpression;
use SqlSemantics\Statement\SemanticGraph;

/**
 * One destination column and the expression requested for its new value.
 * @visibility public
 * @example Distinguishing an assignment from its input expression
 *     is_subclass_of(\SqlSemantics\Statement\Mutation\ColumnAssignment::class, \SqlSemantics\Statement\Expression\ScalarExpression::class) // => false
 */
final class ColumnAssignment
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * The destination lookup belongs to one table; the enclosing operation controls input scope.
     */
    public function __construct(public readonly ColumnReference $column, public readonly ScalarExpression $expression)
    {
        \SqlSemantics\Statement\Validation\Check::input($column->qualifier === null && count($column->scope->tables) === 1, 'A column assignment has an unqualified destination in one target relation.');
        \SqlSemantics\Statement\Validation\Check::input((new SemanticGraph())->containsOnlyValues($expression), 'An assignment retains a semantic expression.');
    }

    /**
     * Reconstructs the request without applying storage conversion or evaluating its expression.
     */
    public function toString(): string
    {
        return $this->column->name->toString() . ' = ' . $this->expression->toString();
    }
}
