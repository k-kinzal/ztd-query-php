<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Lexical;

use SqlSemantics\Diagnostic\AnalysisException;

/**
 * Replaces the Unicode escapes of `U&'...'` strings and `U&"..."` identifiers.
 *
 * Rule: PG-LEX-UNICODE-001. An escape is the escape character followed by
 * four hexadecimal digits, or by `+` and six; the escape character written
 * twice stands for itself. A surrogate pair written as two escapes is one
 * character. The result is UTF-8, the server encoding the profile fixes. The
 * server rejects a malformed escape, a lone surrogate and code point zero
 * while scanning, so they are SQL outside the grammar.
 * Source: https://www.postgresql.org/docs/17/sql-syntax-lexical.html#SQL-SYNTAX-STRINGS-UESCAPE.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class UnicodeEscapes
{
    /**
     * Decodes text whose quotes are already undoubled.
     *
     * @param string $text The text between the quotes
     * @param string $escape The escape character
     */
    public function decode(string $text, string $escape): string
    {
        $result = '';
        $pending = null;
        $length = strlen($text);
        for ($index = 0; $index < $length;) {
            if ($text[$index] !== $escape) {
                $this->reject($pending !== null, 'invalid Unicode surrogate pair');
                $run = strcspn($text, $escape, $index);
                $result .= substr($text, $index, $run);
                $index += $run;
                continue;
            }
            if (($text[$index + 1] ?? '') === $escape) {
                $this->reject($pending !== null, 'invalid Unicode surrogate pair');
                $result .= $escape;
                $index += 2;
                continue;
            }
            $this->reject(preg_match('/\G(?:([0-9A-Fa-f]{4})|\+([0-9A-Fa-f]{6}))/', $text, $match, 0, $index + 1) !== 1, 'invalid Unicode escape');
            $index += 1 + strlen($match[0] ?? '');
            $point = (int) hexdec(($match[2] ?? '') !== '' ? $match[2] : ($match[1] ?? ''));
            if ($pending !== null) {
                $this->reject($point < 0xDC00 || $point > 0xDFFF, 'invalid Unicode surrogate pair');
                $result .= $this->encode(0x10000 + (($pending - 0xD800) << 10) + ($point - 0xDC00));
                $pending = null;
            } elseif ($point >= 0xD800 && $point <= 0xDBFF) {
                $pending = $point;
            } else {
                $result .= $this->encode($point);
            }
        }
        $this->reject($pending !== null, 'invalid Unicode surrogate pair');

        return $result;
    }

    /**
     * Encodes one code point as UTF-8, rejecting the values the server rejects.
     */
    public function encode(int $point): string
    {
        $this->reject($point === 0 || $point > 0x10FFFF || ($point >= 0xD800 && $point <= 0xDFFF), 'invalid Unicode escape value');
        if ($point < 0x80) {
            return chr($point);
        }
        if ($point < 0x800) {
            return chr(0xC0 | ($point >> 6)) . chr(0x80 | ($point & 0x3F));
        }
        if ($point < 0x10000) {
            return chr(0xE0 | ($point >> 12)) . chr(0x80 | (($point >> 6) & 0x3F)) . chr(0x80 | ($point & 0x3F));
        }

        return chr(0xF0 | ($point >> 18)) . chr(0x80 | (($point >> 12) & 0x3F)) . chr(0x80 | (($point >> 6) & 0x3F)) . chr(0x80 | ($point & 0x3F));
    }

    /**
     * Reports text the server rejects while scanning (`str_udeescape` of `parser.c`, called for each `UIDENT` and `USCONST` token).
     *
     * @throws AnalysisException When the condition holds
     */
    public function reject(bool $condition, string $message): void
    {
        if ($condition) {
            throw new AnalysisException($message);
        }
    }
}
