<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Ordering;

use SqlSemantics\Platform\PostgreSql\Ir\Arithmetic;
use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\BuiltinCast;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\CollatedText;
use SqlSemantics\Platform\PostgreSql\Ir\ExpressionLowering;
use SqlSemantics\Platform\PostgreSql\Ir\From\BooleanColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\From\JoinBinding;
use SqlSemantics\Platform\PostgreSql\Ir\From\TextColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\From\WideIntegerColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\ResultColumn;
use SqlSemantics\Platform\PostgreSql\Ir\SourceBinding;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Shape\Fields;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Grouped;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\UnaryOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Limit\LimitCount;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Limit\RowLimit;
use SqlSemantics\Platform\PostgreSql\Statement\Query\NullsOrder;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select as ParsedSelect;
use SqlSemantics\Platform\PostgreSql\Statement\Query\SortDirection;
use SqlSemantics\Platform\PostgreSql\Statement\Query\SortItem;

/**
 * Lowers native sort keys and resolved integer bounds.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class ResultLowering
{
    /**
     * Resolves all checked order keys in precedence order.
     *
     * @param list<ResultColumn> $results The complete SELECT projection when input expressions can sort
     * @return list<Order>
     */
    public function orders(BoundStatement $operation, ParsedSelect $query, SourceBinding|JoinBinding|null $binding, array $results, bool $catalogFirst): array
    {
        $fields = $operation->facts->query($query)->fields();
        Check::input($fields !== null, 'ORDER BY requires a closed result layout.');
        $keys = [];
        foreach ($query->options->order ?? [] as $key) {
            Check::input($key->using === null, 'ORDER BY requires a supported default sort operator.');
            $keys[] = new Order(
                $this->target($key, $operation, $fields, $binding, $results, $catalogFirst, $query->distinct !== null),
                $key->direction === SortDirection::Descending,
                $key->nulls === null ? null : $key->nulls === NullsOrder::First,
            );
        }

        return $keys;
    }

    /**
     * Resolves the exact result position or input expression.
     *
     * @param list<ResultColumn> $results The complete SELECT projection when input expressions can sort
     * @throws InvalidConstruction When a source key cannot belong to a DISTINCT projection
     */
    public function target(SortItem $key, BoundStatement $operation, Fields $fields, SourceBinding|JoinBinding|null $binding, array $results, bool $catalogFirst, bool $distinct): int|Arithmetic|BooleanColumnReference|BuiltinCast|CollatedText|ColumnReference|TextColumnReference|WideIntegerColumnReference
    {
        $resolution = $operation->facts->scalar($key->expression)->resolution;
        if ($resolution instanceof AliasTarget) {
            $position = $resolution->field->position;
            Check::input(($fields->items[$position] ?? null) === $resolution->field, 'ORDER BY must resolve inside the owning result layout.');

            return $position + 1;
        }
        Check::input($binding !== null, 'A grouped ORDER BY needs a projected name or position.');
        if ($resolution instanceof ResolvedColumn) {
            $column = $binding->resolved($resolution);

            if (!$distinct) {
                return $column;
            }
            foreach ($results as $position => $result) {
                $projected = $result->expression;
                if (($projected instanceof ColumnReference || $projected instanceof BooleanColumnReference
                    || $projected instanceof TextColumnReference || $projected instanceof WideIntegerColumnReference)
                    && $projected::class === $column::class
                    && $projected->slot === $column->slot && $projected->occurrence === $column->occurrence) {
                    return $position + 1;
                }
            }
            throw new InvalidConstruction('A DISTINCT SELECT must order by projected positions.');
        }

        Check::input(!$distinct, 'A DISTINCT SELECT must order by projected positions.');
        $expression = (new ExpressionLowering())->lower($key->expression, $binding, $catalogFirst);
        Check::input(
            $expression instanceof Arithmetic || $expression instanceof BuiltinCast || $expression instanceof CollatedText,
            'ORDER BY requires a supported typed input expression.',
        );

        return $expression;
    }

    /**
     * Assigns the effective count and offset by their meaning in the parsed clause.
     */
    public function limit(?RowLimit $clause): ?Limit
    {
        if ($clause === null) {
            return null;
        }
        $count = $clause->count;
        Check::input($count === null || $count instanceof LimitCount, 'A row bound requires LIMIT or OFFSET.');
        $upper = $this->bound($count?->count);
        $offset = $this->bound($clause->offset?->start) ?? 0;

        return $upper === null && $offset === 0 ? null : new Limit($upper, $offset);
    }

    /**
     * Resolves a literal in the registered range, with null denoting an omitted operand.
     */
    public function bound(?Scalar $operand): ?int
    {
        if ($operand === null) {
            return null;
        }
        $negative = false;
        while ($operand instanceof Grouped || ($operand instanceof UnaryOperation
            && $operand->operator->name->value === '-' && !$operand->operator->explicit && $operand->operator->qualifiers === [])) {
            $negative = $negative !== ($operand instanceof UnaryOperation);
            $operand = $operand->operand;
        }
        Check::input($operand instanceof Constant && $operand->value instanceof IntegerConstant, 'A row bound requires a literal signed 32-bit integer.');
        $value = (int) $operand->value->digits;

        return $negative ? -$value : $value;
    }
}
