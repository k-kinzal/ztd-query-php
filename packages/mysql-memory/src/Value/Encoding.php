<?php

declare(strict_types=1);

namespace MySqlMemory\Value;

use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;

/**
 * The bytes of strings in the character sets of the server, and their conversion between sets.
 *
 * A string holds the bytes of its character set: utf8mb4 and utf8mb3 hold UTF-8, latin1 holds
 * Windows-1252 with its five unassigned bytes read as the C1 controls, ucs2, utf16 and utf32 hold
 * big-endian code units, and binary holds bytes. A character the target set cannot hold converts
 * to a question mark. A set PHP has no conversion for is read as ASCII.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/charset-charsets.html,
 * https://dev.mysql.com/doc/refman/8.4/en/charset-conversion.html.
 *
 * @visibility MySqlMemory
 */
final class Encoding
{
    /**
     * The mbstring encoding of each character set, by name.
     */
    public const NAMES = [
        'armscii8' => 'ArmSCII-8', 'ascii' => 'ASCII', 'big5' => 'BIG-5', 'cp1251' => 'Windows-1251', 'cp850' => 'CP850',
        'cp866' => 'CP866', 'cp932' => 'CP932', 'eucjpms' => 'eucJP-win', 'euckr' => 'EUC-KR', 'gb18030' => 'GB18030',
        'gb2312' => 'EUC-CN', 'gbk' => 'CP936', 'greek' => 'ISO-8859-7', 'hebrew' => 'ISO-8859-8', 'koi8r' => 'KOI8-R',
        'koi8u' => 'KOI8-U', 'latin1' => 'Windows-1252', 'latin2' => 'ISO-8859-2', 'latin5' => 'ISO-8859-9',
        'latin7' => 'ISO-8859-13', 'sjis' => 'SJIS', 'ucs2' => 'UCS-2BE', 'ujis' => 'EUC-JP', 'utf16' => 'UTF-16BE',
        'utf16le' => 'UTF-16LE', 'utf32' => 'UTF-32BE', 'utf8mb3' => 'UTF-8', 'utf8mb4' => 'UTF-8',
    ];

    /**
     * Answers the mbstring encoding of a character set: null for binary, ASCII for a set PHP cannot convert.
     */
    public static function name(Charset $charset): ?string
    {
        return $charset->name === 'binary' ? null : self::NAMES[$charset->name] ?? 'ASCII';
    }

    /**
     * Tells whether a character set holds UTF-8.
     */
    public static function utf8(Charset $charset): bool
    {
        return $charset->name === 'utf8mb4' || $charset->name === 'utf8mb3';
    }

    /**
     * Converts the bytes of a string from one character set to another.
     *
     * A binary string is taken, or given, as its bytes.
     */
    public static function convert(string $text, Charset $from, Charset $to): string
    {
        $source = self::name($from);
        $target = self::name($to);
        if ($source === null || $target === null || $from === $to || ($source === 'UTF-8' && $to->name === 'utf8mb4')) {
            return $text;
        }
        if ($source === 'ASCII' && $from->name !== 'ascii') {
            $text = (string) preg_replace('/[\x80-\xFF]/', '?', $text);
        }
        $converted = (string) mb_convert_encoding($text, $target, $source);

        return $to->name === 'utf8mb3' ? (string) preg_replace('/[\xF0-\xF4][\x80-\xBF]{3}/', '?', $converted) : $converted;
    }

    /**
     * Tells whether the bytes of a string are characters of a character set.
     */
    public static function valid(string $text, Charset $charset): bool
    {
        $name = self::name($charset);
        if ($name === null) {
            return true;
        }
        if ($charset->name === 'utf8mb3' && preg_match('/[\xF0-\xF4]/', $text) === 1) {
            return false;
        }

        return mb_check_encoding($text, $name);
    }

    /**
     * Answers the length in bytes of the longest start of a string that is whole characters of a character set.
     */
    public static function prefix(string $text, Charset $charset): int
    {
        $length = strlen($text);
        while ($length > 0 && !self::valid(substr($text, 0, $length), $charset)) {
            $length--;
        }

        return $length;
    }

    /**
     * Answers the length in bytes of the start of a string whose characters another character set holds.
     */
    public static function convertible(string $text, Charset $from, Charset $to): int
    {
        $offset = 0;
        foreach (self::characters($text, $from) as $character) {
            if (self::convert(self::convert($character, $from, $to), $to, $from) !== $character) {
                return $offset;
            }
            $offset += strlen($character);
        }

        return $offset;
    }

    /**
     * Splits a string into the characters of its character set; bytes that are no character split one by one.
     *
     * @return list<string>
     */
    public static function characters(string $text, Charset $charset): array
    {
        $name = self::name($charset);
        if ($text === '') {
            return [];
        }
        if ($name === null || $charset->maxLength === 1 || !mb_check_encoding($text, $name)) {
            return str_split($text);
        }

        return mb_str_split($text, 1, $name);
    }

    /**
     * Counts the characters of a string in its character set.
     */
    public static function length(string $text, Charset $charset): int
    {
        $name = self::name($charset);
        if ($name === null || $charset->maxLength === 1 || !mb_check_encoding($text, $name)) {
            return strlen($text);
        }

        return mb_strlen($text, $name);
    }

    /**
     * Answers the characters of a string from a position counted from 0, at most a number of them.
     */
    public static function slice(string $text, int $start, ?int $length, Charset $charset): string
    {
        $name = self::name($charset);
        if ($name === null || $charset->maxLength === 1 || !mb_check_encoding($text, $name)) {
            return substr($text, $start, $length);
        }

        return mb_substr($text, $start, $length, $name);
    }
}
