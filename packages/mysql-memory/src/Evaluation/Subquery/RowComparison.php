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
 * An empty row subquery makes ordinary comparison unknown. Null-safe equality also reads
 * retained projection expressions; modern releases compare their NULL states, while legacy
 * releases compare their values. Table-column states and some versioned set-operation forms remain incomplete.
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
     * @param bool $legacyValues Whether empty projections retain value comparison, as in MySQL 5.6 and 5.7
     */
    public function __construct(public readonly ComparisonOperator $operator, public readonly array|SingleRow $left, public readonly array|SingleRow $right, public readonly array $comparators, public readonly Domain $domain, public readonly bool $legacyValues = false)
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
            return $this->operator === ComparisonOperator::NullSafeEqual ? $this->absent($left, $right, $frame) : null;
        }
        $pairs = [];
        foreach ($this->comparators as $index => $comparator) {
            $pairs[] = [new Constant($comparator->left, $left[$index]), new Constant($comparator->right, $right[$index]), $comparator];
        }

        return (new RowCompare($this->operator, $pairs, $this->domain))->evaluate($frame);
    }

    /**
     * Compares expressions retained by an empty subquery, without evaluating later columns early.
     *
     * Modern releases compare the NULL states and retain the final pair's NULL state; legacy
     * releases compare the retained values. Ordinary comparisons never read this projection.
     *
     * @param list<int|float|string|null> $left The returned left row, or none
     * @param list<int|float|string|null> $right The returned right row, or none
     */
    public function absent(array $left, array $right, Frame $frame): int
    {
        $one = $left === [] && $this->left instanceof SingleRow ? $this->left->projection : [];
        $two = $right === [] && $this->right instanceof SingleRow ? $this->right->projection : [];
        if (($left === [] && $one === []) || ($right === [] && $two === [])) {
            return (int) ($this->legacyValues && $left === [] && $right === []);
        }
        $equal = false;
        foreach ($this->comparators as $index => $comparator) {
            $first = $left === [] ? $this->left->value($index, $frame) : $left[$index];
            $second = $right === [] ? $this->right->value($index, $frame) : $right[$index];
            if (($first === null) !== ($second === null)) {
                return 0;
            }
            $equal = $first === null;
            if ($this->legacyValues && !$equal && $comparator->compare($first, $second, $frame->context) !== 0) {
                return 0;
            }
        }

        return (int) ($this->legacyValues || $equal);
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
