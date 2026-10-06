<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Literal;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\RadixSpelling;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * A number at a position that is not an expression: an option value, a count, a size, an identifier of a thread or server.
 *
 * The digits are kept exactly. The grammar also accepts a hexadecimal
 * literal at these positions, and at some of them a decimal or floating
 * number that the server then rejects; each is kept as written.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/number-literals.html.
 *
 * @visibility public
 * @example Holding the exact text of a number
 *     $number = new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('007');
 *     [$number->text, $number->hexadecimal] // => ['007', false]
 */
final class Numeral implements Node
{
    use Snapshot;

    /**
     * @param string $text The number exactly as written, or the digits of a hexadecimal literal
     * @param bool $hexadecimal Whether the text is the digits of a hexadecimal literal
     */
    public function __construct(public readonly string $text, public readonly bool $hexadecimal = false)
    {
        Check::input(
            preg_match($hexadecimal ? '/\A[0-9A-Fa-f]*\z/' : '/\A(?:[0-9]+\.?[0-9]*|\.[0-9]+)(?:[eE][+-]?[0-9]+)?\z/', $text) === 1,
            'A numeral is an unsigned number or the digits of a hexadecimal literal.',
        );
    }

    /**
     * Writes the number, a hexadecimal one in the spelling its digit count permits.
     */
    public function render(Output $out): void
    {
        $out->spelled($this->hexadecimal ? (new RadixSpelling())->hexadecimal($this->text) : $this->text);
    }
}
