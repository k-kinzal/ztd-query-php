<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Literal;

use SqlSemantics\Contract\LexicalSettings;

/**
 * How a backslash inside a quoted string is read: as an escape character or as itself.
 *
 * The session mode NO_BACKSLASH_ESCAPES decides it, and the language profile
 * fixes that mode. A string value holds the rule it is spelled under, because
 * the same bytes are spelled differently under each rule; a value built for
 * the other rule than the profile of its operation is refused when the
 * operation is constructed.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/sql-mode.html#sqlmode_no_backslash_escapes.
 *
 * @visibility public
 * @example Choosing the rule of a profile
 *     \SqlSemantics\Platform\MySql\Statement\Literal\EscapeRule::under(new \SqlSemantics\Contract\LexicalSettings('NO_BACKSLASH_ESCAPES')) // => \SqlSemantics\Platform\MySql\Statement\Literal\EscapeRule::Verbatim
 */
enum EscapeRule
{
    case Backslash;
    case Verbatim;

    /**
     * Answers the rule the lexical settings of a profile select.
     */
    public static function under(LexicalSettings $lexical): self
    {
        return $lexical->noBackslashEscapes ? self::Verbatim : self::Backslash;
    }
}
