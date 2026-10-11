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
 * Compares a row constructor or row subquery with each row of an IN subquery using three-valued equality.
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
     * @param list<Evaluable>|SingleRow $operands The row constructor's elements or a row subquery
     * @param Rows $rows The subquery's planned rows
     * @param list<Comparator> $comparators The comparison rule for each column pair
     * @param bool $negated Whether this is NOT IN
     * @param Domain $domain The truth value's reported type
     * @param list<int> $key The integer primary-key comparisons that restrict the candidate rows
     * @param bool $once Whether the independent keyed result is retained for the statement
     * @param bool $legacyCondition Whether a satisfied constant-table condition is evaluated again when read, as in MySQL 5.6 and 5.7
     * @param bool $repeatCondition Whether MySQL 5.6 retests a satisfied cached condition for each outer row
     */
    public function __construct(public readonly array|SingleRow $operands, public readonly Rows $rows, public readonly array $comparators, public readonly bool $negated, public readonly Domain $domain, public readonly array $key = [], public readonly bool $once = false, public readonly bool $legacyCondition = false, public readonly bool $repeatCondition = false)
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
     * Retains an independent keyed result in its statement context, including NULL results.
     * MySQL 5.6 retests a satisfied constant-table condition on each use, retaining
     * its candidate row and operands while preserving the repeated conversion warnings.
     */
    #[Override]
    public function evaluate(Frame $frame): ?int
    {
        if (!$this->once) {
            return $this->read($frame);
        }
        $kept = $frame->context->kept;
        if (!isset($kept[$this])) {
            $kept[$this] = [$this->read($frame)];
        } elseif ($this->repeatCondition && isset($kept[$this->rows])) {
            $width = count($this->comparators);
            $this->matches(array_slice($kept[$this->rows], 0, $width), array_slice($kept[$this->rows], $width), $frame->context);
        }

        return $kept[$this][0] === null ? null : (int) $kept[$this][0];
    }

    /**
     * Searches candidate rows. A false constant condition eliminates its row before execution;
     * legacy releases evaluate a satisfied condition again when consuming the constant row.
     * Verified through SQL and EXPLAIN on MySQL 5.6, 5.7, 8.0 and 9.1.
     */
    public function read(Frame $frame): ?int
    {
        $values = $this->operands instanceof SingleRow ? $this->operands->read($frame) : array_map(static fn (Evaluable $operand): int|float|string|null => $operand->evaluate($frame), $this->operands);
        $values = $values === [] ? array_fill(0, count($this->comparators), null) : $values;
        $kept = $frame->context->kept;
        $iterator = $this->rows->start($frame);
        $unknown = false;
        while (($row = $iterator->read()) !== null) {
            $match = $this->matches($values, $row, $frame->context);
            if ($match === true) {
                if ($this->repeatCondition) {
                    $kept[$this->rows] = [...$values, ...array_slice($row, 0, count($this->comparators))];
                }
                if ($this->legacyCondition) {
                    $this->matches($values, $row, $frame->context);
                }
                return $this->negated ? 0 : 1;
            }
            if ($match === null && in_array(null, $values, true)) {
                if ($this->once && array_intersect($this->key, array_keys($values, null, true)) === []) {
                    $this->matches($values, $row, $frame->context);
                }
                return null;
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
        $key = array_intersect($this->key, array_keys($values, null, true)) === [] ? $this->key : [];
        foreach ($key as $index) {
            $order = $this->comparators[$index]->compare($values[$index], $row[$index], $context);
            if ($order !== null && $order !== 0) {
                return false;
            }
        }
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
