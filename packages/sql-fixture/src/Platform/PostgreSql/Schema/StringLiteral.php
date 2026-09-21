<?php

declare(strict_types=1);

namespace SqlFixture\Platform\PostgreSql\Schema;

use SqlFixture\Syntax\CodePoint;
use SqlFixture\Syntax\QuotedText;
use SqlParser\Lexer\Token;

/**
 * Decodes a PostgreSQL string constant token into the text it denotes.
 *
 * A constant is written plainly, with C escapes after an E, with Unicode
 * escapes after a U&, or between dollar quotes, and each spells its text a
 * different way.
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
        if (strtoupper(substr($text, 0, 3)) === "U&'") {
            return (new Identifier())->unescape((new QuotedText())->unquote(substr($text, 2)));
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
            $coded = $this->coded($inner, $index, $escaped);
            if ($coded !== null) {
                $result .= $coded[0];
                $index += $coded[1];
                continue;
            }
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

    /**
     * Answers the bytes an escape written as a number spells and how many characters it took, or null for another escape.
     *
     * @return array{string, int}|null The bytes and the characters read after the escape character
     */
    public function coded(string $inner, int $index, string $escaped): ?array
    {
        $digits = match (true) {
            $escaped === 'x' => $this->digits($inner, $index + 1, 2, 'ctype_xdigit'),
            $escaped === 'u' => $this->digits($inner, $index + 1, 4, 'ctype_xdigit'),
            $escaped === 'U' => $this->digits($inner, $index + 1, 8, 'ctype_xdigit'),
            ctype_digit($escaped) && (int) $escaped < 8 => $escaped . $this->digits($inner, $index + 1, 2, static fn (string $text): bool => $text !== '' && strspn($text, '01234567') === strlen($text)),
            default => null,
        };
        if ($digits === null || $digits === '') {
            return null;
        }
        if ($escaped === 'x' || $escaped === 'u' || $escaped === 'U') {
            return [(new CodePoint())->utf8((int) hexdec($digits)), strlen($digits)];
        }

        return [chr((int) octdec($digits) % 256), strlen($digits) - 1];
    }

    /**
     * Answers the longest run of up to a given length the test accepts, starting at an offset.
     *
     * @param callable(string): bool $accepts Reports whether a run is written the way the escape needs
     */
    public function digits(string $inner, int $offset, int $length, callable $accepts): string
    {
        for ($taken = $length; $taken > 0; $taken--) {
            $run = substr($inner, $offset, $taken);
            if (strlen($run) === $taken && $accepts($run)) {
                return $run;
            }
        }

        return '';
    }
}
