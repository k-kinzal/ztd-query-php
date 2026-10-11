<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Subquery;

use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Evaluation\Operator\Comparison\Comparator;
use MySqlMemory\Evaluation\Operator\Comparison\RowCompare;
use MySqlMemory\Typing\Domain;
use Override;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;

/**
 * Compares row constructors and single-row subqueries after evaluating each operand once.
 *
 * An empty row subquery makes ordinary comparison unknown and null-safe equality false,
 * including when both subqueries are empty (verified through SQL on MySQL 9.1).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/row-subqueries.html.
 *
 * @visibility MySqlMemory
 */
final class RowComparison implements Evaluable
{
    /**
     * @param list<Evaluable>|SingleRow $left The left row or subquery
     * @param list<Evaluable>|SingleRow $right The right row or subquery
     * @param list<Comparator> $comparators The comparison rule for each column pair
     * @param Domain $domain The resolved truth value type
     */
    public function __construct(public readonly ComparisonOperator $operator, public readonly array|SingleRow $left, public readonly array|SingleRow $right, public readonly array $comparators, public readonly Domain $domain)
    {
    }

    /**
     * Answers the truth value's domain.
     */
    #[Override]
    public function domain(): Domain
    {
        return $this->domain;
    }

    /**
     * Evaluates both rows and applies the element-wise comparison rules.
     */
    #[Override]
    public function evaluate(Frame $frame): ?int
    {
        $left = $this->values($this->left, $frame);
        $right = $this->values($this->right, $frame);
        if ($left === [] || $right === []) {
            return $this->operator === ComparisonOperator::NullSafeEqual ? 0 : null;
        }
        $pairs = [];
        foreach ($this->comparators as $index => $comparator) {
            $pairs[] = [new Constant($comparator->left, $left[$index]), new Constant($comparator->right, $right[$index]), $comparator];
        }

        return (new RowCompare($this->operator, $pairs, $this->domain))->evaluate($frame);
    }

    /**
     * Reads the row's values, preserving an empty subquery as an empty list.
     *
     * @param list<Evaluable>|SingleRow $operand The compiled row operand
     * @return list<int|float|string|null>
     */
    public function values(array|SingleRow $operand, Frame $frame): array
    {
        return $operand instanceof SingleRow ? $operand->read($frame) : array_map(static fn (Evaluable $value): int|float|string|null => $value->evaluate($frame), $operand);
    }
}
