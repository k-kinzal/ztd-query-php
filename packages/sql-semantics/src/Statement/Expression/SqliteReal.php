<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Expression;

use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Declaration\TypeDescriptor;
use SqlSemantics\Statement\Literal\NumberLiteral;

/**
 * A decimal floating-point literal before SQLite rounds it to binary64.
 * @visibility public
 * @example Keeping the exact decimal input
 *     (new \SqlSemantics\Statement\Expression\SqliteReal('1_000.25e-2'))->value->value() // => '1000.25e-2'
 */
final class SqliteReal implements ScalarExpression
{
    /**
     * Exact decimal input, independent of the host machine's floating-point rounding.
     */
    public readonly NumberLiteral $value;

    /**
     * A real numeral contains a decimal point or exponent, with separators only between digits.
     */
    public function __construct(public readonly string $numeral)
    {
        assert(strpbrk($numeral, '.eE') !== false, 'A real numeral contains a decimal point or exponent.');
        assert(!str_starts_with($numeral, '-'), 'Unary negation is a separate operation.');
        assert(preg_match('/(?<![0-9])_|_(?![0-9])/', $numeral) === 0, 'Separators must occur singly between decimal digits.');
        $this->value = new NumberLiteral(str_replace('_', '', $numeral));
    }

    /**
     * A decimal point or exponent selects SQLite's binary64 storage class.
     */
    public function type(): TypeDescriptor
    {
        return new TypeDescriptor(Builtin::DoublePrecision);
    }

    /**
     * Underflow and overflow produce zero or infinity, never NULL.
     */
    public function nullability(): Nullability
    {
        return Nullability::NotNull;
    }

    /**
     * A literal has no declaration dependencies.
     * @return list<ColumnReference>
     */
    public function references(): array
    {
        return [];
    }

    /**
     * Retains numeral spelling, which also determines an unaliased output name.
     */
    public function toString(): string
    {
        return $this->numeral;
    }
}
