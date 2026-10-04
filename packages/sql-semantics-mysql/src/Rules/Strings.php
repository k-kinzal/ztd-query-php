<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules;

/**
 * Decodes and spells the quoted string tokens of MySQL under a fixed escape setting.
 *
 * Rule: MYSQL-STRING-DECODE-001. Scope: the terminals TEXT_STRING and
 * NCHAR_STRING. The text between the quotes is read byte by byte. A doubled
 * quote character is one quote. Unless NO_BACKSLASH_ESCAPES is set, a
 * backslash and the byte after it are one escape: `\0` is NUL, `\b`
 * backspace, `\n` newline, `\r` carriage return, `\t` tab, `\Z` byte 0x1A,
 * `\%` and `\_` keep the backslash, and before any other byte the backslash
 * is dropped. Under NO_BACKSLASH_ESCAPES a backslash is an ordinary byte.
 * The spelling writes single quotes, doubles each single quote and, when
 * backslash is an escape, doubles each backslash; that spelling decodes to
 * the same bytes. Assumption: the client character set does not use the
 * bytes of a backslash or a quote inside a multi-byte character (true for
 * utf8mb4, utf8mb3, latin1 and ASCII; not for sjis, big5, gbk, gb18030,
 * cp932). Terminates: one pass over the text.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/string-literals.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Strings
{
    /**
     * The escape letters whose meaning is another byte.
     */
    private const ESCAPES = ['0' => "\0", 'b' => "\x08", 'n' => "\n", 'r' => "\r", 't' => "\t", 'Z' => "\x1A", '%' => '\\%', '_' => '\\_'];

    /**
     * The bytes a backslash-escaping writer spells as an escape sequence: the backslash itself and the control bytes the manual names.
     */
    private const SPELLINGS = ['\\' => '\\\\', "\0" => '\\0', "\n" => '\\n', "\r" => '\\r', "\x1A" => '\\Z'];

    /**
     * Decodes a quoted string token, with or without the `N` prefix, to its bytes.
     */
    public function decode(string $text, bool $backslashEscapes): string
    {
        $start = ($text[0] ?? '') === 'N' || ($text[0] ?? '') === 'n' ? 1 : 0;
        $quote = $text[$start] ?? '';
        $end = strlen($text) - 1;
        $value = '';
        for ($index = $start + 1; $index < $end; $index++) {
            $byte = $text[$index];
            if ($backslashEscapes && $byte === '\\' && $index + 1 < $end) {
                $index++;
                $value .= self::ESCAPES[$text[$index]] ?? $text[$index];
            } elseif ($byte === $quote && $index + 1 < $end && $text[$index + 1] === $quote) {
                $index++;
                $value .= $quote;
            } else {
                $value .= $byte;
            }
        }

        return $value;
    }

    /**
     * Spells bytes as a single-quoted string that decodes to the same bytes.
     */
    public function encode(string $value, bool $backslashEscapes): string
    {
        $body = $backslashEscapes ? strtr($value, self::SPELLINGS) : $value;

        return "'" . str_replace("'", "''", $body) . "'";
    }
}
