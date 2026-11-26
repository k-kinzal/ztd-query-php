<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Literal;

use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Node;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Rules\Lexical\Numerals;

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
 * @visibility SqlSemantics
 * @example Rejecting an integer the scanner reads as an integer constant
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NumericConstant('100') // throws \SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction
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
     * Returns canonical decimal digits for an integer token.
     */
    public function integerDigits(): ?string
    {
        return (new Numerals())->integer($this->text);
    }

    /**
     * Writes the text as it was written.
     */
    public function render(Output $out): void
    {
        $out->spelled($this->text);
    }
}
