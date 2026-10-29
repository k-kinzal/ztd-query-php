<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation;

use InvalidArgumentException;
use MySqlMemory\Value\Decimal;
use Stringable;

/**
 * An exact intermediate number with separate numeric and text representations.
 *
 * Numeric consumers read all calculation digits. Text consumers round at the
 * expression's resolved scale. Keeping both with the value also preserves the
 * chosen branch's text scale through IF and CASE without evaluating it again.
 * Source: MySQL 8.4.7 Item_func_numhybrid::val_str and Item_func_if::val_str.
 *
 * @visibility MySqlMemory
 */
final class ExactDecimal implements Stringable
{
    /**
     * @throws InvalidArgumentException When the text scale is outside the MySQL decimal range
     * @param string $number The unrounded decimal calculation result
     * @param int $scale The fractional digits used when the expression is read as text
     */
    public function __construct(public readonly string $number, public readonly int $scale)
    {
        if ($scale < 0 || $scale > 30) {
            throw new InvalidArgumentException('A decimal text scale is between zero and thirty.');
        }
    }

    /**
     * Supplies all calculation digits to numeric consumers.
     */
    public function __toString(): string
    {
        return $this->number;
    }

    /**
     * Unwraps calculation digits when an existing scalar operation consumes a number.
     *
     * @phpstan-pure
     */
    public static function raw(int|float|string|self|null $value): int|float|string|null
    {
        return $value instanceof self ? $value->number : $value;
    }

    /**
     * Formats the value only when a consumer reads it as text.
     */
    public function text(): string
    {
        return Decimal::round($this->number, $this->scale);
    }
}
