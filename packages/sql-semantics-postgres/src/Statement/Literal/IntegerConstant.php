<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Literal;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * The value of a numeric constant written as an integer, of any size.
 *
 * Decimal, hexadecimal, octal and binary digits and underscores between
 * digits are spellings of the same integer; the value is kept as canonical
 * decimal digits and written back that way.
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
     * @param string $digits The decimal digits without leading zeros
     */
    public function __construct(public readonly string $digits)
    {
        Check::input(preg_match('/\A(?:0|[1-9][0-9]*)\z/', $digits) === 1, 'An integer constant is canonical decimal digits.');
    }

    /**
     * Writes the decimal digits.
     */
    public function render(Output $out): void
    {
        $out->spelled($this->digits);
    }
}
