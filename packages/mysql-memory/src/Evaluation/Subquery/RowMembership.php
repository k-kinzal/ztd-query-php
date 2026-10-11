<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Subquery;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Operator\Comparison\Comparator;
use MySqlMemory\Typing\Domain;
use Override;

/**
 * Compares a row constructor with each row of an IN subquery using three-valued equality.
 *
 * An unequal pair makes its row false even when another pair is NULL. An equal row wins over
 * unknown rows; an empty subquery is false, including for a row whose elements are all NULL.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/row-subqueries.html.
 *
 * @visibility MySqlMemory
 */
final class RowMembership implements Evaluable
{
    /**
     * @param list<Evaluable> $operands The row constructor's elements
     * @param Rows $rows The subquery's planned rows
     * @param list<Comparator> $comparators The comparison rule for each column pair
     * @param bool $negated Whether this is NOT IN
     * @param Domain $domain The truth value's reported type
     */
    public function __construct(public readonly array $operands, public readonly Rows $rows, public readonly array $comparators, public readonly bool $negated, public readonly Domain $domain)
    {
    }

    /**
     * Answers the domain of the truth value.
     */
    #[Override]
    public function domain(): Domain
    {
        return $this->domain;
    }

    /**
     * Reads the left row once and searches the subquery, retaining conversion warnings.
     */
    #[Override]
    public function evaluate(Frame $frame): ?int
    {
        $values = array_map(static fn (Evaluable $operand): int|float|string|null => $operand->evaluate($frame), $this->operands);
        $iterator = $this->rows->start($frame);
        $unknown = false;
        while (($row = $iterator->read()) !== null) {
            $match = $this->matches($values, $row, $frame->context);
            if ($match === true) {
                return $this->negated ? 0 : 1;
            }
            $unknown = $unknown || $match === null;
        }

        return $unknown ? null : ($this->negated ? 1 : 0);
    }

    /**
     * Compares corresponding columns, retaining unknown only when no pair is unequal.
     *
     * @param list<int|float|string|null> $values The left row's values
     * @param list<int|float|string|null> $row The subquery row's values
     */
    public function matches(array $values, array $row, Context $context): ?bool
    {
        $unknown = false;
        foreach ($this->comparators as $index => $comparator) {
            $order = $comparator->compare($values[$index], $row[$index], $context);
            if ($order !== null && $order !== 0) {
                return false;
            }
            $unknown = $unknown || $order === null;
        }

        return $unknown ? null : true;
    }
}
