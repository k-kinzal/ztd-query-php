<?php

declare(strict_types=1);

namespace SqlFixture\Platform\Sqlite\Schema;

use SqlFixture\Syntax\QuotedText;
use SqlParser\Lexer\Token;

/**
 * Reads the name an identifier token spells, without its quotes or brackets.
 *
 * @visibility root
 */
final class Identifier
{
    /**
     * Returns the identifier as written, unfolding double quotes, backticks, brackets and string quotes.
     */
    public function decode(Token $token): string
    {
        $opening = substr($token->text, 0, 1);

        return in_array($opening, ['"', '`', '[', "'"], true) ? (new QuotedText())->unquote($token->text) : $token->text;
    }
}
