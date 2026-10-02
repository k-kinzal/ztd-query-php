<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules;

/**
 * The character set names a string, hexadecimal or bit literal may be introduced with.
 *
 * Rule: MYSQL-INTRODUCER-001. `_name` before a literal is a character set
 * introducer only when the name is a character set the server compiles in;
 * any other such word is an identifier. The list is the one the lexer of the
 * pinned parser uses for MySQL 5.6 through 9.1. Names are compared without
 * regard to ASCII case and held in lower case.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/charset-introducer.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Introducers
{
    /**
     * The character set names, in lower case.
     */
    private const NAMES = [
        'armscii8', 'ascii', 'big5', 'binary', 'cp1250', 'cp1251', 'cp1256', 'cp1257', 'cp850', 'cp852',
        'cp866', 'cp932', 'dec8', 'eucjpms', 'euckr', 'gb18030', 'gb2312', 'gbk', 'geostd8', 'greek',
        'hebrew', 'hp8', 'keybcs2', 'koi8r', 'koi8u', 'latin1', 'latin2', 'latin5', 'latin7', 'macce',
        'macroman', 'sjis', 'swe7', 'tis620', 'ucs2', 'ujis', 'utf16', 'utf16le', 'utf32', 'utf8',
        'utf8mb3', 'utf8mb4',
    ];

    /**
     * Answers the character set an introducer token names, in lower case.
     */
    public function charset(string $token): string
    {
        return strtr(substr($token, 1), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    /**
     * Tells whether a lower-case name can be written as an introducer.
     */
    public function known(string $name): bool
    {
        return in_array($name, self::NAMES, true);
    }
}
