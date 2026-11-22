<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Ordering;

use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Grouped;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\UnaryOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\OutputPosition;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Limit\LimitCount;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Limit\RowLimit;
use SqlSemantics\Platform\PostgreSql\Statement\Query\NullsOrder;
use SqlSemantics\Platform\PostgreSql\Statement\Query\SortDirection;
use SqlSemantics\Platform\PostgreSql\Statement\Query\SortItem;

/**
 * Lowers ordered result positions and resolved integer bounds.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class ResultLowering
{
    /**
     * Resolves the positional key established by binding and its NULL placement.
     */
    public function order(SortItem $key): Order
    {
        Check::input($key->expression instanceof OutputPosition && $key->using === null, 'ORDER BY requires a supported output position.');

        return new Order(
            (int) $key->expression->value(),
            $key->direction === SortDirection::Descending,
            $key->nulls === null ? null : $key->nulls === NullsOrder::First,
        );
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
