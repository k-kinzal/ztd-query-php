<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Literal;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;

/**
 * A string at a position that is not an expression: a comment, a file name, a password, a separator, a member of an ENUM.
 *
 * It is written as a quoted string or, where the grammar allows it, as a
 * hexadecimal or bit literal that stands for a string. The value is the
 * decoded bytes of a quoted string, or the digits of the other two forms. It
 * is an operand of the statement that holds it and has no type fact of its
 * own.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/string-literals.html,
 * https://dev.mysql.com/doc/refman/8.4/en/hexadecimal-literals.html.
 *
 * @visibility public
 * @example Holding decoded text
 *     (new \SqlSemantics\Platform\MySql\Statement\Literal\Text("it's"))->value // => "it's"
 */
final class Text implements Node
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * The retained radix notation; absent for ordinary character text and decimal numbers.
     */
    public readonly ?RadixNotation $notation;

    /**
     * @param string $value The decoded bytes of a quoted string, or the digits of a hexadecimal or bit literal
     * @param Radix|null $radix The digit system when the text is written as a hexadecimal or bit literal
     */
    public function __construct(public readonly string $value, public readonly ?Radix $radix = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null, ?RadixNotation $notation = null, public readonly ?Quoted\Notation $quotation = null)
    {
        Check::input($radix === null || $quotation === null, 'Only quoted text has string quotation choices.');
        $quotation?->validate($value);
        Check::input($radix !== null || $notation === null, 'Only radix values have a radix notation.');
        $this->notation = $radix === null ? null : ($notation ?? RadixNotation::canonical($radix, $value));
        Check::input($radix === null || $this->notation?->permits($radix, $value) === true, 'A radix notation accepts the literal digit count.');
        Check::input($radix !== Radix::Hexadecimal || preg_match('/\A[0-9A-Fa-f]*\z/', $value) === 1, 'A hexadecimal text holds hexadecimal digits.');
        Check::input($radix !== Radix::Bit || preg_match('/\A[01]*\z/', $value) === 1, 'A bit text holds binary digits.');
    }

    /**
     * Writes the spelling that reads back as the same value.
     */
    public function render(Output $out): void
    {
        $out->spelled($this->radix !== null && $this->notation !== null ? $this->notation->spell($this->radix, $this->value) : ($this->quotation?->spell($this->value) ?? $out->quote($this->value)));
    }

    /**
     * Answers the bytes the text denotes, decoding hexadecimal and bit digits when present.
     */
    public function bytes(): string
    {
        return match ($this->radix) {
            null => $this->value,
            Radix::Hexadecimal => (string) hex2bin(strlen($this->value) % 2 === 1 ? '0' . $this->value : $this->value),
            Radix::Bit => implode('', array_map(static fn (string $octet): string => chr((int) bindec($octet)), $this->value === '' ? [] : str_split(str_pad($this->value, (int) ceil(strlen($this->value) / 8) * 8, '0', STR_PAD_LEFT), 8))),
        };
    }
}
