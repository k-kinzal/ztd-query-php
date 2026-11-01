<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Literal\Quoted;

/**
 * The interpretation of a backslash escape inside a MySQL quoted string.
 *
 * Discarded means the backslash is ignored and the following byte is literal.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/string-literals.html.
 * @example
 *     strlen(\SqlSemantics\Platform\MySql\Statement\Literal\Quoted\Escape::ControlZ->bytes()) // => 1
 *
 * @visibility public
 */
enum Escape: string
{
    case Nul = '0';
    case Backspace = 'b';
    case Newline = 'n';
    case CarriageReturn = 'r';
    case Tab = 't';
    case ControlZ = 'Z';
    case Backslash = '\\';
    case SingleQuote = "'";
    case DoubleQuote = '"';
    case Percent = '%';
    case Underscore = '_';
    case Discarded = '';

    /**
     * Returns the decoded bytes, or null when the following byte supplies them.
     */
    public function bytes(): ?string
    {
        return match ($this) {
            self::Nul => "\0",
            self::Backspace => "\x08",
            self::Newline => "\n",
            self::CarriageReturn => "\r",
            self::Tab => "\t",
            self::ControlZ => "\x1a",
            self::Percent => '\\%',
            self::Underscore => '\\_',
            self::Discarded => null,
            self::Backslash, self::SingleQuote, self::DoubleQuote => $this->value,
        };
    }
}
