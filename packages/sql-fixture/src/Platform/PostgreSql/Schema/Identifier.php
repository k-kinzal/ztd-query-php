<?php

declare(strict_types=1);

namespace SqlFixture\Platform\PostgreSql\Schema;

use SqlFixture\Syntax\CodePoint;
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
     * Returns the identifier as written, unfolding a quoted name and its Unicode escapes.
     */
    public function decode(Token $token): string
    {
        $text = $token->text;
        if ($this->isUnicodeQuoted($text)) {
            return $this->unescape((new QuotedText())->unquote(substr($text, 2)));
        }

        return str_starts_with($text, '"') ? (new QuotedText())->unquote($text) : $text;
    }

    /**
     * Returns the name the server stores: a quoted identifier as written, an unquoted one folded.
     */
    public function fold(Token $token): string
    {
        return $this->isQuoted($token->text) ? $this->decode($token) : strtolower($token->text);
    }

    /**
     * Reports whether the name was written with quotes of either kind.
     */
    public function isQuoted(string $text): bool
    {
        return str_starts_with($text, '"') || $this->isUnicodeQuoted($text);
    }

    /**
     * Reports whether the name was written as a Unicode escaped identifier.
     */
    public function isUnicodeQuoted(string $text): bool
    {
        return strtoupper(substr($text, 0, 3)) === 'U&"';
    }

    /**
     * Resolves the Unicode escapes of a quoted name written with the default escape character.
     */
    public function unescape(string $inner): string
    {
        $result = '';
        $length = strlen($inner);
        for ($index = 0; $index < $length; $index++) {
            if ($inner[$index] !== '\\') {
                $result .= $inner[$index];
                continue;
            }
            if (($inner[$index + 1] ?? '') === '\\') {
                $result .= '\\';
                $index++;
                continue;
            }
            $long = substr($inner, $index + 2, 6);
            if (($inner[$index + 1] ?? '') === '+' && strlen($long) === 6 && ctype_xdigit($long)) {
                $result .= (new CodePoint())->utf8((int) hexdec($long));
                $index += 7;
                continue;
            }
            $short = substr($inner, $index + 1, 4);
            if (strlen($short) === 4 && ctype_xdigit($short)) {
                $result .= (new CodePoint())->utf8((int) hexdec($short));
                $index += 4;
                continue;
            }
            $result .= $inner[$index];
        }

        return $result;
    }

    /**
     * Writes a name back as one quoted identifier, doubling the quote the way the server reads it.
     */
    public function quote(string $name): string
    {
        return '"' . str_replace('"', '""', $name) . '"';
    }
}
