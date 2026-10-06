<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Lexical;

use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Diagnostic\Check;

/**
 * Decodes the spelling of a PostgreSQL string constant token into its value.
 *
 * Rule: PG-LEX-STRING-001. Scope: the `SCONST` terminal in its four
 * spellings and the `BCONST` and `XCONST` terminals. `'...'` reads a doubled
 * quote as one quote and nothing else, because the profile fixes
 * `standard_conforming_strings = on`. `E'...'` additionally reads the
 * backslash escapes of the manual. `$tag$...$tag$` is taken literally.
 * `U&'...'` reads Unicode escapes with the escape character of its `UESCAPE`
 * clause. Quoted segments separated by whitespace that contains a newline are
 * one constant. An escape string that produces a zero byte or bytes that are
 * not UTF-8 is rejected by the server scanner and is SQL outside the grammar.
 * Source: https://www.postgresql.org/docs/17/sql-syntax-lexical.html#SQL-SYNTAX-CONSTANTS.
 * Termination: one pass over the token text.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Strings
{
    /**
     * The single-character escapes of an escape string.
     */
    private const SINGLE = ['b' => "\x08", 'f' => "\f", 'n' => "\n", 'r' => "\r", 't' => "\t", 'v' => "\v"];

    /**
     * Decodes the text of a string constant token.
     *
     * @throws AnalysisException When the scanner `scan.l` rejects the constant (invalid encoding of an escape string, invalid Unicode escape)
     */
    public function decode(string $text): string
    {
        if (str_starts_with($text, '$')) {
            $tag = (int) strpos($text, '$', 1) + 1;

            return substr($text, $tag, strlen($text) - 2 * $tag);
        }
        if (preg_match('/\A[uU]&/', $text) === 1) {
            [$value, $rest] = $this->quoted($text, 2, false);
            $escape = trim($rest) === '' ? '\\' : substr($rest, -2, 1);

            return (new UnicodeEscapes())->decode($value, $escape);
        }
        if (preg_match('/\A[eE]/', $text) === 1) {
            $value = $this->quoted($text, 1, true)[0];
            if (str_contains($value, "\0") || preg_match('//u', $value) !== 1) {
                throw new AnalysisException('invalid byte sequence for encoding "UTF8" in an escape string constant');
            }

            return $value;
        }

        return $this->quoted($text, 0, false)[0];
    }

    /**
     * Answers the digits written between the quotes of a bit-string or hexadecimal constant token.
     */
    public function digits(string $text): string
    {
        $digits = '';
        $index = 1;
        while (true) {
            $end = strpos($text, "'", $index + 1);
            Check::invariant($end !== false, 'A bit-string token closes its quote.');
            $digits .= substr($text, $index + 1, $end - $index - 1);
            $next = $this->continuation($text, $end + 1);
            if ($next === null) {
                return $digits;
            }
            $index = $next;
        }
    }

    /**
     * Reads the quoted segments that start at an offset and answers the value and the text after them.
     *
     * @return array{string, string}
     */
    public function quoted(string $text, int $offset, bool $escapes): array
    {
        $value = '';
        $index = $offset;
        $stops = $escapes ? "'\\" : "'";
        while (true) {
            $index++;
            while (true) {
                $run = strcspn($text, $stops, $index);
                $value .= substr($text, $index, $run);
                $index += $run;
                Check::invariant($index < strlen($text), 'A string token closes its quote.');
                if ($text[$index] === '\\') {
                    [$piece, $index] = $this->escape($text, $index);
                    $value .= $piece;
                    continue;
                }
                if (($text[$index + 1] ?? '') === "'") {
                    $value .= "'";
                    $index += 2;
                    continue;
                }
                $index++;
                break;
            }
            $next = $this->continuation($text, $index);
            if ($next === null) {
                return [$value, substr($text, $index)];
            }
            $index = $next;
        }
    }

    /**
     * Finds the quote that continues a constant after whitespace and comments, or null.
     */
    public function continuation(string $text, int $offset): ?int
    {
        if (preg_match('/\G(?:\s|--[^\n\r]*)*\'/', $text, $match, 0, $offset) !== 1) {
            return null;
        }

        return $offset + strlen($match[0]) - 1;
    }

    /**
     * Decodes the backslash escape at an offset of an escape string.
     *
     * @return array{string, int} The bytes it stands for and the offset after it
     *
     * @throws AnalysisException When the `xeunicode` rules of the scanner `scan.l` reject the escape
     */
    public function escape(string $text, int $offset): array
    {
        if (preg_match('/\G\\\\(?:([0-7]{1,3})|x([0-9A-Fa-f]{1,2})|u([0-9A-Fa-f]{4})|U([0-9A-Fa-f]{8}))/', $text, $match, 0, $offset) !== 1) {
            $character = $text[$offset + 1] ?? '';
            if ($character === 'u' || $character === 'U') {
                throw new AnalysisException('invalid Unicode escape');
            }

            return [self::SINGLE[$character] ?? $character, $offset + 2];
        }
        $next = $offset + strlen($match[0]);
        if ($match[1] !== '') {
            return [chr((int) octdec($match[1]) & 0xFF), $next];
        }
        if ($match[2] !== '') {
            return [chr((int) hexdec($match[2])), $next];
        }
        $point = (int) hexdec(($match[4] ?? '') !== '' ? $match[4] : $match[3]);
        if ($point >= 0xD800 && $point <= 0xDBFF) {
            if (preg_match('/\G\\\\(?:u([0-9A-Fa-f]{4})|U([0-9A-Fa-f]{8}))/', $text, $pair, 0, $next) !== 1) {
                throw new AnalysisException('invalid Unicode surrogate pair');
            }
            $low = (int) hexdec(($pair[2] ?? '') !== '' ? $pair[2] : $pair[1]);
            if ($low < 0xDC00 || $low > 0xDFFF) {
                throw new AnalysisException('invalid Unicode surrogate pair');
            }

            return [(new UnicodeEscapes())->encode(0x10000 + (($point - 0xD800) << 10) + ($low - 0xDC00)), $next + strlen($pair[0])];
        }

        return [(new UnicodeEscapes())->encode($point), $next];
    }
}
