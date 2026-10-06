<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Literal;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Lexical\Numerals;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * The value of a numeric constant written as an integer that fits 32 bits.
 *
 * The scanner reads such a constant as an `ICONST` holding the integer, so
 * decimal, hexadecimal, octal and binary digits and underscores between
 * digits are spellings of the same value; it is kept as canonical decimal
 * digits and written back that way. A larger integer is an `FCONST` the
 * server keeps as written, a `NumericConstant` (PG-LEX-NUMBER-001).
 * Source: https://www.postgresql.org/docs/17/sql-syntax-lexical.html#SQL-SYNTAX-CONSTANTS-NUMERIC.
 *
 * @visibility public
 * @example Reading a hexadecimal integer
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT 0x1_F');
 *     $query->statement->targets[0]->expression->value->digits // => '31'
 * @example Rejecting digits that are not canonical
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('007') // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class IntegerConstant implements Node
{
    use Snapshot;

    /**
     * @param string $digits The decimal digits without leading zeros, at most 2147483647
     */
    public function __construct(public readonly string $digits)
    {
        Check::input(preg_match('/\A(?:0|[1-9][0-9]*)\z/', $digits) === 1, 'An integer constant is canonical decimal digits.');
        Check::input((new Numerals())->within($digits, '2147483647'), 'An integer constant fits 32 bits; a larger integer is a numeric constant.');
    }

    /**
     * Writes the decimal digits.
     */
    public function render(Output $out): void
    {
        $out->spelled($this->digits);
    }
}
