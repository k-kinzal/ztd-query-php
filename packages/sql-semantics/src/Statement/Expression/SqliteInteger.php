<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Expression;

use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Declaration\TypeDescriptor;
use SqlSemantics\Statement\Literal\NumberLiteral;
use SqlSemantics\Statement\Literal\Radix;
use SqlSemantics\Statement\Literal\UnsignedInteger;
use SqlSemantics\Statement\Type\Invalid;

/**
 * SQLite's integer literal interpretation, including signed hexadecimal bit patterns.
 * @visibility public
 * @example Reading an exact hexadecimal value with SQLite's signed interpretation
 *     $literal = new \SqlSemantics\Statement\Expression\SqliteInteger(new \SqlSemantics\Statement\Literal\UnsignedInteger('ffffffffffffffff', \SqlSemantics\Statement\Literal\Radix::Hexadecimal));
 *     $literal->value->value() // => '-1'
 */
final class SqliteInteger implements ScalarExpression
{
    /**
     * Exact decimal text before any floating-point conversion by the database.
     */
    public readonly NumberLiteral $value;

    /**
     * Whether a hexadecimal value exceeds the database's literal width.
     */
    public readonly bool $overflow;

    /**
     * Derives value and width facts from digits rather than accepting independently supplied facts.
     */
    public function __construct(public readonly UnsignedInteger $integer, public readonly bool $negative = false, public readonly bool $uppercasePrefix = false)
    {
        assert(in_array($integer->radix, [Radix::Decimal, Radix::Hexadecimal], true), 'This integer literal uses decimal or hexadecimal notation.');
        assert(!$uppercasePrefix || $integer->radix === Radix::Hexadecimal, 'Only a hexadecimal literal has a radix prefix.');
        $digits = ltrim(strtolower(str_replace('_', '', $integer->digits)), '0');
        $decimal = $integer->decimal();
        if ($integer->radix === Radix::Hexadecimal && strlen($digits) === 16 && hexdec($digits[0]) >= 8) {
            $complement = strtr($digits, '0123456789abcdef', 'fedcba9876543210');
            $decimal = '-' . (new UnsignedInteger($complement, Radix::Hexadecimal))->successor()->decimal();
        }
        $this->overflow = $integer->radix === Radix::Hexadecimal && (strlen($digits) > 16 || ($negative && $decimal === '-9223372036854775808'));
        if ($negative) {
            $decimal = str_starts_with($decimal, '-') ? substr($decimal, 1) : '-' . $decimal;
        }
        $this->value = new NumberLiteral($decimal);
    }

    /**
     * Distinguishes integers, decimal literals promoted to real, and invalid hexadecimal widths.
     */
    public function type(): TypeDescriptor|Invalid
    {
        if ($this->overflow) {
            return Invalid::IntegerLiteralOverflow;
        }
        $digits = ltrim($this->value->value, '-');
        $limit = str_starts_with($this->value->value, '-') ? '9223372036854775808' : '9223372036854775807';
        $fits = strlen($digits) < 19 || (strlen($digits) === 19 && strcmp($digits, $limit) <= 0);
        return new TypeDescriptor($fits ? Builtin::Integer : Builtin::DoublePrecision);
    }

    /**
     * A valid numeric literal is non-NULL; an invalid literal cannot produce a value.
     */
    public function nullability(): Nullability
    {
        return $this->overflow ? Nullability::Unknown : Nullability::NotNull;
    }

    /**
     * A numeric literal has no column lookup or declaration dependency.
     * @return list<ColumnReference>
     */
    public function references(): array
    {
        return [];
    }

    /**
     * Reconstructs the numeral from its sign, base, prefix case, and digit grouping.
     */
    public function toString(): string
    {
        $prefix = $this->integer->radix === Radix::Decimal ? '' : ($this->uppercasePrefix ? '0X' : '0x');
        return ($this->negative ? '-' : '') . $prefix . $this->integer->digits;
    }
}
