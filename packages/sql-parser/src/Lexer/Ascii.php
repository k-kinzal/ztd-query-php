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

    /**
     * Recognizes one ASCII letter without requiring the ctype extension.
     */
    public static function letter(string $byte): bool
    {
        return strlen($byte) === 1 && (($byte >= 'A' && $byte <= 'Z') || ($byte >= 'a' && $byte <= 'z'));
    }

    /**
     * Recognizes one decimal digit; empty lookahead is never a digit.
     */
    public static function digit(string $byte): bool
    {
        return strlen($byte) === 1 && $byte >= '0' && $byte <= '9';
    }

    /**
     * Recognizes SQL whitespace bytes independently of a host character locale.
     */
    public static function space(string $byte): bool
    {
        return strlen($byte) === 1 && str_contains(" \t\n\r\f\v", $byte);
    }

    /**
     * Recognizes a single ASCII control byte, including DEL.
     */
    public static function control(string $byte): bool
    {
        return strlen($byte) === 1 && (ord($byte) < 32 || ord($byte) === 127);
    }
}
