<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Aggregation;

use SqlSemantics\Platform\MySql\Ir\BooleanLiteral;
use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\ExpressionLowering;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerOperator;
use SqlSemantics\Platform\MySql\Ir\Predicate\RangeIntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\SourceBinding;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate as ParsedAggregate;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison as ParsedComparison;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Literal\BooleanLiteral as ParsedBoolean;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral as ParsedNull;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;

/**
 * Resolves a checked HAVING predicate in the semantic group scope.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class HavingLowering
{
    /**
     * Binds the optional predicate before the group input's final slot layout is published.
     * @param list<GroupColumn> $columns Resolved exposed values by result position
     */
    public function lower(?Scalar $condition, BoundStatement $operation, SourceBinding $binding, AggregateBinding $aggregate, array $columns): GroupedComparison|BooleanLiteral|NullLiteral|null
    {
        while ($condition instanceof Grouped) {
            $condition = $condition->operand;
        }
        if ($condition === null) {
            return null;
        }
        if ($condition instanceof ParsedNull) {
            return new NullLiteral();
        }
        if ($condition instanceof ParsedBoolean) {
            return new BooleanLiteral($condition->value);
        }
        Check::input($condition instanceof ParsedComparison, 'HAVING needs a numeric group comparison, a Boolean constant or NULL.');
        $operator = IntegerOperator::from($condition->operator->value);

        return new GroupedComparison(
            $operator,
            $this->operand($condition->left, $operation, $binding, $aggregate, $columns),
            $this->operand($condition->right, $operation, $binding, $aggregate, $columns)
        );
    }

    /**
     * Projects an already-resolved result alias or lowers a direct group operand.
     * @param list<GroupColumn> $columns Resolved exposed values by result position
     */
    public function operand(Scalar $operand, BoundStatement $operation, SourceBinding $binding, AggregateBinding $aggregate, array $columns): Aggregate|ColumnReference|GroupNameReference|IntegerLiteral|NullLiteral|RangeIntegerLiteral
    {
        while ($operand instanceof Grouped) {
            $operand = $operand->operand;
        }
        if ($operand instanceof ParsedAggregate) {
            return $aggregate->lower($operand, $binding);
        }
        if ($operand instanceof ColumnUse) {
            $resolution = $operation->facts->scalar($operand)->resolution;
            if ($resolution instanceof AliasTarget) {
                $column = $columns[$resolution->field->position] ?? null;
                Check::input($column !== null, 'A HAVING alias needs an exact projected value.');

                return $column->value;
            }

            $column = $binding->reference($operand);
            $name = $operand->qualifier === null ? new Identifier($operand->name->value) : null;

            return $name !== null && (new GroupNameScope())->collides($column, $name, $columns)
                ? new GroupNameReference($column, $name) : $column;
        }
        $value = (new ExpressionLowering())->lower($operand, $binding, true);
        Check::input(
            $value instanceof IntegerLiteral || $value instanceof RangeIntegerLiteral || $value instanceof NullLiteral,
            'A HAVING comparison needs a numeric group value, an integer literal or SQL NULL.'
        );

        return $value;
    }
}
