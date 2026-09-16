<?php

declare(strict_types=1);

namespace SqlParser\MySql\Lexer;

/**
 * The character set names an underscore introducer may name.
 *
 * `_utf8mb4'text'` is read as an introducer only when the word after the
 * underscore is a character set MySQL knows; any other such word is an
 * ordinary identifier.
 *
 * @visibility root
 */
final class Charsets
{
    /**
     * Character set names MySQL 5.6 through 9.x compile in.
     */
    public const NAMES = [
        'armscii8', 'ascii', 'big5', 'binary', 'cp1250', 'cp1251', 'cp1256', 'cp1257', 'cp850', 'cp852',
        'cp866', 'cp932', 'dec8', 'eucjpms', 'euckr', 'gb18030', 'gb2312', 'gbk', 'geostd8', 'greek',
        'hebrew', 'hp8', 'keybcs2', 'koi8r', 'koi8u', 'latin1', 'latin2', 'latin5', 'latin7', 'macce',
        'macroman', 'sjis', 'swe7', 'tis620', 'ucs2', 'ujis', 'utf16', 'utf16le', 'utf32', 'utf8',
        'utf8mb3', 'utf8mb4',
    ];

    /**
     * Reports whether a word names a character set.
     *
     * @param string $name Word without the leading underscore
     *
     * @return bool True for a known character set, in any case
     */
    public static function has(string $name): bool
    {
        return in_array(strtolower($name), self::NAMES, true);
    }
}
