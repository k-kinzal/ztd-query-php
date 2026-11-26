<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Binding;

/**
 * The native lexical modes admitted by complete MySQL projections.
 *
 * @visibility public
 * @example Choosing the mode that treats double quotes as identifier delimiters
 *     \SqlSemantics\Platform\MySql\Ir\Binding\ProjectionMode::AnsiQuotes->value // => 'ANSI_QUOTES'
 */
enum ProjectionMode: string
{
    case Empty = 'empty';
    case AnsiQuotes = 'ANSI_QUOTES';
    case NoBackslashEscapes = 'NO_BACKSLASH_ESCAPES';
    case AnsiQuotesAndNoBackslashEscapes = 'ANSI_QUOTES,NO_BACKSLASH_ESCAPES';

    /**
     * Determines whether a plain text literal spells backslashes as escapes.
     */
    public function escapesBackslash(): bool
    {
        return match ($this) {
            self::Empty, self::AnsiQuotes => true,
            self::NoBackslashEscapes, self::AnsiQuotesAndNoBackslashEscapes => false,
        };
    }
}
