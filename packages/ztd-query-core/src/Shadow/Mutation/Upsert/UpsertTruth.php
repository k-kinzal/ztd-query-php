<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Mutation\Upsert;

use ZtdQuery\Connection\StatementInterface;
use ZtdQuery\Exception\UnsupportedSqlException;

/**
 * Answers whether a value counts as true, in SQL's three-valued logic.
 *
 * Null is not false: it is unknown, and it stays unknown through NOT. It only
 * stops mattering where the other operand settles the answer on its own — one
 * false makes an AND false, one true makes an OR true — which is what makes
 * these different from PHP's own operators.
 *
 * @phpstan-import-type RowValue from StatementInterface
 */
final class UpsertTruth
{
    /**
     * @param UpsertNumber $numbers Reads a value as the number it stands for
     */
    public function __construct(private readonly UpsertNumber $numbers = new UpsertNumber())
    {
    }

    /**
     * Answers whether a value counts as true.
     *
     * @param RowValue $value Value to read
     *
     * @return bool|null Whether it does, or null when the value is unknown
     *
     * @throws UnsupportedSqlException When the value is neither boolean nor a number
     */
    public function of(int|float|string|bool|null $value): ?bool
    {
        if ($value === null) {
            return null;
        }
        if (is_bool($value)) {
            return $value;
        }

        return !in_array($this->numbers->of($value), [0, 0.0], true);
    }

    /**
     * Answers the opposite of what a value counts as.
     *
     * @param RowValue $value Value to read
     *
     * @return bool|null The opposite, or null when the value is unknown
     *
     * @throws UnsupportedSqlException When the value is neither boolean nor a number
     */
    public function not(int|float|string|bool|null $value): ?bool
    {
        $truth = $this->of($value);

        return $truth === null ? null : !$truth;
    }

    /**
     * Answers whether both sides hold.
     *
     * @param bool|null $left Left operand
     * @param bool|null $right Right operand
     *
     * @return bool|null Whether both do, or null when that cannot be said
     */
    public function and(?bool $left, ?bool $right): ?bool
    {
        if ($left === false || $right === false) {
            return false;
        }
        if ($left === null || $right === null) {
            return null;
        }

        return true;
    }

    /**
     * Answers whether either side holds.
     *
     * @param bool|null $left Left operand
     * @param bool|null $right Right operand
     *
     * @return bool|null Whether either does, or null when that cannot be said
     */
    public function or(?bool $left, ?bool $right): ?bool
    {
        if ($left === true || $right === true) {
            return true;
        }
        if ($left === null || $right === null) {
            return null;
        }

        return false;
    }
}
