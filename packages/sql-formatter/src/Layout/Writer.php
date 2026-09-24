<?php

declare(strict_types=1);

namespace SqlFormatter\Layout;

use SqlParser\Lexer\Token;

/**
 * Writes chosen whitespace while retaining comment-bearing trivia verbatim.
 *
 * @visibility SqlFormatter
 */
final class Writer
{
    private string $text = '';

    /**
     * Appends a token using layout whitespace or its original comment-bearing trivia.
     */
    public function token(Token $token, string $separator): void
    {
        if (trim($token->leading) !== '') {
            $this->text .= $token->leading;
        } elseif ($token->text !== '') {
            $this->text .= $this->text === '' ? ltrim($separator, "\r\n") : $separator;
        }
        $this->text .= $token->text;
    }

    /**
     * Returns the result with any final comments preserved.
     */
    public function finish(string $trailing): string
    {
        return $this->text . (trim($trailing) === '' ? '' : $trailing);
    }
}
