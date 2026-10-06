<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Literal;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * The value of a bit-string constant: its notation and the digits between the quotes.
 *
 * The scanner accepts any characters between the quotes and the `bit` input
 * function interprets them later, so the digits are opaque text here; a
 * hexadecimal digit stands for four bits.
 * Source: https://www.postgresql.org/docs/17/sql-syntax-lexical.html#SQL-SYNTAX-BIT-STRINGS.
 *
 * @visibility public
 * @example Reading a hexadecimal bit string
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("SELECT X'1F'");
 *     $value = $query->statement->targets[0]->expression->value;
 *     [$value->radix, $value->digits] // => [\SqlSemantics\Platform\PostgreSql\Statement\Literal\BitStringRadix::Hexadecimal, '1F']
 */
final class BitStringConstant implements Node
{
    use Snapshot;

    /**
     * @param BitStringRadix $radix The notation of the digits
     * @param string $digits The text between the quotes
     */
    public function __construct(public readonly BitStringRadix $radix, public readonly string $digits)
    {
        Check::input(!str_contains($digits, "'") && !str_contains($digits, "\0"), 'A bit-string constant holds no quote and no zero byte.');
    }

    /**
     * Writes the notation prefix and the quoted digits.
     */
    public function render(Output $out): void
    {
        $out->spelled($this->radix->value . "'" . $this->digits . "'");
    }
}
