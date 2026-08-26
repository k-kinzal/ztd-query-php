<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Mutation\Upsert;

use ZtdQuery\Connection\StatementInterface;
use ZtdQuery\Exception\UnsupportedSqlException;

/**
 * Does arithmetic the way a database does it, over the values a row carries.
 *
 * Two things separate this from PHP's own operators. A row value arrives as
 * text however it was declared, so '12' is a number here; and null is unknown
 * rather than zero, so anything touching it is unknown too.
 *
 * @phpstan-import-type RowValue from StatementInterface
 */
final class UpsertNumber
{
    /**
     * Reads a value as the number it stands for.
     *
     * A string is read as an integer unless it is written the way a float is,
     * which keeps whole numbers exact however the driver handed them over.
     *
     * @param RowValue $value Value to read
     *
     * @return int|float The number it stands for
     *
     * @throws UnsupportedSqlException When the value is not written as a number
     */
    public function of(int|float|string|bool|null $value): int|float
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }
        if (!is_string($value) || !is_numeric($value)) {
            throw new UnsupportedSqlException('non-numeric UPSERT operand', 'Unsupported UPSERT expression');
        }

        return strpbrk($value, '.eE') === false ? (int) $value : (float) $value;
    }

    /**
     * Reports whether a value is one arithmetic can be done on.
     *
     * @param RowValue $value Value to test
     *
     * @return bool True when it is a number or written as one
     */
    public function isNumeric(int|float|string|bool|null $value): bool
    {
        if (is_int($value) || is_float($value)) {
            return true;
        }

        return is_string($value) && is_numeric($value);
    }

    /**
     * Answers the value with its sign kept, as a number.
     *
     * @param RowValue $value Value to read
     *
     * @return int|float|null The number, or null when the value is unknown
     *
     * @throws UnsupportedSqlException When the value is not written as a number
     */
    public function positive(int|float|string|bool|null $value): int|float|null
    {
        return $value === null ? null : $this->of($value);
    }

    /**
     * Answers the value with its sign turned around.
     *
     * @param RowValue $value Value to read
     *
     * @return int|float|null The number, or null when the value is unknown
     *
     * @throws UnsupportedSqlException When the value is not written as a number
     */
    public function negative(int|float|string|bool|null $value): int|float|null
    {
        return $value === null ? null : -$this->of($value);
    }

    /**
     * Adds two values.
     *
     * @param RowValue $left Left operand
     * @param RowValue $right Right operand
     *
     * @return int|float|null The sum, or null when either is unknown
     *
     * @throws UnsupportedSqlException When either is not written as a number
     */
    public function add(int|float|string|bool|null $left, int|float|string|bool|null $right): int|float|null
    {
        if ($left === null || $right === null) {
            return null;
        }

        return $this->of($left) + $this->of($right);
    }

    /**
     * Subtracts the right value from the left.
     *
     * @param RowValue $left Left operand
     * @param RowValue $right Right operand
     *
     * @return int|float|null The difference, or null when either is unknown
     *
     * @throws UnsupportedSqlException When either is not written as a number
     */
    public function subtract(int|float|string|bool|null $left, int|float|string|bool|null $right): int|float|null
    {
        if ($left === null || $right === null) {
            return null;
        }

        return $this->of($left) - $this->of($right);
    }

    /**
     * Multiplies two values.
     *
     * @param RowValue $left Left operand
     * @param RowValue $right Right operand
     *
     * @return int|float|null The product, or null when either is unknown
     *
     * @throws UnsupportedSqlException When either is not written as a number
     */
    public function multiply(int|float|string|bool|null $left, int|float|string|bool|null $right): int|float|null
    {
        if ($left === null || $right === null) {
            return null;
        }

        return $this->of($left) * $this->of($right);
    }

    /**
     * Divides the left value by the right.
     *
     * @param RowValue $left Left operand
     * @param RowValue $right Right operand
     *
     * @return int|float|null The quotient, or null when either is unknown
     *
     * @throws UnsupportedSqlException When either is not a number, or the divisor is zero
     */
    public function divide(int|float|string|bool|null $left, int|float|string|bool|null $right): int|float|null
    {
        if ($left === null || $right === null) {
            return null;
        }
        $divisor = $this->of($right);
        if ($divisor === 0 || $divisor === 0.0) {
            throw new UnsupportedSqlException(
                'division by zero in UPSERT expression',
                'Unsupported UPSERT expression',
            );
        }

        return $this->of($left) / $divisor;
    }

    /**
     * Answers what is left over when the left value is divided by the right.
     *
     * Both sides are taken as whole numbers first, which is what SQL's own
     * modulo does with a fractional operand.
     *
     * @param RowValue $left Left operand
     * @param RowValue $right Right operand
     *
     * @return int|null The remainder, or null when either is unknown
     *
     * @throws UnsupportedSqlException When either is not a number, or the divisor is zero
     */
    public function modulo(int|float|string|bool|null $left, int|float|string|bool|null $right): ?int
    {
        if ($left === null || $right === null) {
            return null;
        }
        $divisor = intval($this->of($right));
        if ($divisor === 0) {
            throw new UnsupportedSqlException(
                'division by zero in UPSERT expression',
                'Unsupported UPSERT expression',
            );
        }

        return intval($this->of($left)) % $divisor;
    }
}
