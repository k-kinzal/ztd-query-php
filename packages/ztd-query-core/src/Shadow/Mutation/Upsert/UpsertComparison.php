<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Mutation\Upsert;

use ZtdQuery\Connection\StatementInterface;
use ZtdQuery\Exception\UnsupportedSqlException;

/**
 * Compares two values the way a database does it.
 *
 * Numbers are compared as numbers and text as text; comparing one against the
 * other is refused rather than guessed at, because which of the two a database
 * would coerce depends on the column types and the collation, neither of which
 * is known here. Null is unknown, so every comparison against it is unknown.
 *
 * @phpstan-import-type RowValue from StatementInterface
 */
final class UpsertComparison
{
    /**
     * @param UpsertNumber $numbers Reads a value as the number it stands for
     */
    public function __construct(private readonly UpsertNumber $numbers = new UpsertNumber())
    {
    }

    /**
     * Answers how two values order against each other.
     *
     * @param RowValue $left Left operand
     * @param RowValue $right Right operand
     *
     * @return int|null Negative, zero or positive, or null when either is unknown
     *
     * @throws UnsupportedSqlException When one side is a number and the other is not
     */
    public function of(int|float|string|bool|null $left, int|float|string|bool|null $right): ?int
    {
        if ($left === null || $right === null) {
            return null;
        }
        if ($this->numbers->isNumeric($left)) {
            if (!$this->numbers->isNumeric($right)) {
                throw new UnsupportedSqlException(
                    'incomparable UPSERT operands',
                    'Unsupported UPSERT expression',
                );
            }

            return $this->numbers->of($left) <=> $this->numbers->of($right);
        }
        if ($this->numbers->isNumeric($right)) {
            throw new UnsupportedSqlException('incomparable UPSERT operands', 'Unsupported UPSERT expression');
        }

        return $this->text($left) <=> $this->text($right);
    }

    /**
     * Reads a value as the text it stands for.
     *
     * A boolean is the one non-text value that still has a text form here,
     * because that is the form a database compares it in.
     *
     * @param RowValue $value Value to read
     *
     * @return string The text it stands for
     *
     * @throws UnsupportedSqlException When the value has no text form
     */
    public function text(int|float|string|bool|null $value): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_bool($value)) {
            return $value ? '1' : '';
        }

        throw new UnsupportedSqlException('incomparable UPSERT operands', 'Unsupported UPSERT expression');
    }

    /**
     * Reports whether two values are the same.
     *
     * @param RowValue $left Left operand
     * @param RowValue $right Right operand
     *
     * @return bool|null Whether they are, or null when either is unknown
     *
     * @throws UnsupportedSqlException When one side is a number and the other is not
     */
    public function equal(int|float|string|bool|null $left, int|float|string|bool|null $right): ?bool
    {
        $order = $this->of($left, $right);

        return $order === null ? null : $order === 0;
    }

    /**
     * Reports whether two values differ.
     *
     * @param RowValue $left Left operand
     * @param RowValue $right Right operand
     *
     * @return bool|null Whether they do, or null when either is unknown
     *
     * @throws UnsupportedSqlException When one side is a number and the other is not
     */
    public function notEqual(int|float|string|bool|null $left, int|float|string|bool|null $right): ?bool
    {
        $order = $this->of($left, $right);

        return $order === null ? null : $order !== 0;
    }

    /**
     * Reports whether the left value orders before the right.
     *
     * @param RowValue $left Left operand
     * @param RowValue $right Right operand
     *
     * @return bool|null Whether it does, or null when either is unknown
     *
     * @throws UnsupportedSqlException When one side is a number and the other is not
     */
    public function less(int|float|string|bool|null $left, int|float|string|bool|null $right): ?bool
    {
        $order = $this->of($left, $right);

        return $order === null ? null : $order < 0;
    }

    /**
     * Reports whether the left value orders before the right, or with it.
     *
     * @param RowValue $left Left operand
     * @param RowValue $right Right operand
     *
     * @return bool|null Whether it does, or null when either is unknown
     *
     * @throws UnsupportedSqlException When one side is a number and the other is not
     */
    public function lessOrEqual(int|float|string|bool|null $left, int|float|string|bool|null $right): ?bool
    {
        $order = $this->of($left, $right);

        return $order === null ? null : $order <= 0;
    }

    /**
     * Reports whether the left value orders after the right.
     *
     * @param RowValue $left Left operand
     * @param RowValue $right Right operand
     *
     * @return bool|null Whether it does, or null when either is unknown
     *
     * @throws UnsupportedSqlException When one side is a number and the other is not
     */
    public function greater(int|float|string|bool|null $left, int|float|string|bool|null $right): ?bool
    {
        $order = $this->of($left, $right);

        return $order === null ? null : $order > 0;
    }

    /**
     * Reports whether the left value orders after the right, or with it.
     *
     * @param RowValue $left Left operand
     * @param RowValue $right Right operand
     *
     * @return bool|null Whether it does, or null when either is unknown
     *
     * @throws UnsupportedSqlException When one side is a number and the other is not
     */
    public function greaterOrEqual(int|float|string|bool|null $left, int|float|string|bool|null $right): ?bool
    {
        $order = $this->of($left, $right);

        return $order === null ? null : $order >= 0;
    }
}
