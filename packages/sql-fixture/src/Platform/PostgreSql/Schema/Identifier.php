<?php

declare(strict_types=1);

namespace SqlFixture\Platform\PostgreSql\Schema;

use SqlFixture\Syntax\QuotedText;
use SqlParser\Lexer\Token;

/**
 * Reads the name an identifier token spells, without its quotes.
 *
 * @visibility root
 */
final class Identifier
{
    /**
     * Returns the identifier as written, unfolding a double-quoted name.
     */
    public function decode(Token $token): string
    {
        return str_starts_with($token->text, '"') ? (new QuotedText())->unquote($token->text) : $token->text;
    }
}
