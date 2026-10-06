<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Literal;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Lexical\Numerals;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * A numeric constant the server keeps as its written text: one written with a decimal point or an exponent, or an integer too large for 32 bits.
 *
 * The scanner reads such a constant as an `FCONST`, and the raw parser
 * stores it as a `T_Float` node holding the written text, not a number. The
 * text is the value: an expression reads it as `bigint` or `numeric` when
 * its type is known, where `0001.50` is the numeric 1.50 with two fraction
 * digits, but `SET`, storage parameters, trigger arguments and other options
 * receive the text verbatim, so `SET application_name = 1e2` sets `1e2` and
 * `0x1FFFFFFFFF` stays `0x1FFFFFFFFF`. The text is kept exactly, digit
 * separators, letter case and leading zeros included, and written back as it
 * is (PG-LEX-NUMBER-001).
 * Source: https://www.postgresql.org/docs/17/sql-syntax-lexical.html#SQL-SYNTAX-CONSTANTS-NUMERIC,
 * `makeFloat` and `NumericOnly` in `src/backend/parser/gram.y` of PostgreSQL 17.
 *
 * @visibility public
 * @example Reading the text of a numeric constant
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT 1_0.50e-3');
 *     $query->statement->targets[0]->expression->value->text // => '1_0.50e-3'
 * @example Rejecting an integer the scanner reads as an integer constant
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NumericConstant('100') // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class NumericConstant implements Node
{
    use Snapshot;

    /**
     * @param string $text The written text, without a sign
     */
    public function __construct(public readonly string $text)
    {
        Check::input((new Numerals())->kept($text), 'A numeric constant is a number with a point or an exponent, or an integer beyond 32 bits, as the scanner reads it.');
    }

    /**
     * Writes the text as it was written.
     */
    public function render(Output $out): void
    {
        $out->spelled($this->text);
    }
}
