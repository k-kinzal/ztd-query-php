<?php

declare(strict_types=1);

namespace SqlFixture\Platform\PostgreSql\Schema;

use SqlFixture\Syntax\QuotedText;
use SqlParser\Lexer\Token;

/**
 * Decodes a PostgreSQL string constant token into the text it denotes.
 *
 * @visibility root
 */
final class StringLiteral
{
    /**
     * Reads a standard, escape or dollar-quoted SCONST token.
     */
    public function decode(Token $token): string
    {
        $text = $token->text;
        if (str_starts_with($text, '$')) {
            $tagEnd = strpos($text, '$', 1);
            if ($tagEnd === false) {
                return $text;
            }
            $tagLength = $tagEnd + 1;

            return substr($text, $tagLength, -$tagLength);
        }
        if (str_starts_with($text, "'")) {
            return (new QuotedText())->unquote($text);
        }
        if (strtoupper(substr($text, 0, 1)) === 'E') {
            return $this->unescape((new QuotedText())->unquote(substr($text, 1)));
        }

        return $text;
    }

    /**
     * Resolves the C-style escapes of an E'...' string.
     */
    public function unescape(string $inner): string
    {
        $result = '';
        $length = strlen($inner);
        for ($index = 0; $index < $length; $index++) {
            $character = $inner[$index];
            if ($character !== '\\' || $index + 1 >= $length) {
                $result .= $character;
                continue;
            }
            $escaped = $inner[++$index];
            $result .= match ($escaped) {
                'b' => "\x08",
                'f' => "\x0c",
                'n' => "\n",
                'r' => "\r",
                't' => "\t",
                default => $escaped,
            };
        }

        return $result;
    }
}
