<?php

declare(strict_types=1);

namespace SqlParser\Lexer;

/**
 * Fixed ASCII case conversion, independent of the host locale on PHP 8.1 too.
 * @visibility SqlParser
 * @see https://www.php.net/manual/en/function.strtolower.php#refsect1-function.strtolower-changelog
 */
final class Ascii
{
    /**
     * Converts only bytes A through Z and preserves every other byte.
     */
    public static function lower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    /**
     * Converts only bytes a through z without consulting mutable process settings.
     */
    public static function upper(string $value): string
    {
        return strtr($value, 'abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ');
    }
}
