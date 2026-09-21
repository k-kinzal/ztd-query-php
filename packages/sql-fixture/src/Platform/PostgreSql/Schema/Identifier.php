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

    /**
     * Returns the name the server stores: a quoted identifier as written, an unquoted one folded.
     */
    public function fold(Token $token): string
    {
        return str_starts_with($token->text, '"') ? $this->decode($token) : strtolower($token->text);
    }

    /**
     * Writes a name back as one quoted identifier, doubling the quote the way the server reads it.
     */
    public function quote(string $name): string
    {
        return '"' . str_replace('"', '""', $name) . '"';
    }
}
