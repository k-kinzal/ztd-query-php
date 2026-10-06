<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Literal;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\RadixSpelling;
use SqlSemantics\Platform\MySql\Rules\Strings;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

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
    use Snapshot;

    /**
     * @param string $value The decoded bytes of a quoted string, or the digits of a hexadecimal or bit literal
     * @param EscapeRule $escapes The rule a quoted string is spelled under; the rule of the language profile
     * @param Radix|null $radix The digit system when the text is written as a hexadecimal or bit literal
     */
    public function __construct(public readonly string $value, public readonly EscapeRule $escapes = EscapeRule::Backslash, public readonly ?Radix $radix = null)
    {
        Check::input($radix !== Radix::Hexadecimal || preg_match('/\A[0-9A-Fa-f]*\z/', $value) === 1, 'A hexadecimal text holds hexadecimal digits.');
        Check::input($radix !== Radix::Bit || preg_match('/\A[01]*\z/', $value) === 1, 'A bit text holds binary digits.');
    }

    /**
     * Writes the spelling that reads back as the same value.
     */
    public function render(Output $out): void
    {
        $out->spelled(match ($this->radix) {
            null => (new Strings())->encode($this->value, $this->escapes === EscapeRule::Backslash),
            Radix::Hexadecimal => (new RadixSpelling())->hexadecimal($this->value),
            Radix::Bit => (new RadixSpelling())->bits($this->value),
        });
    }
}
