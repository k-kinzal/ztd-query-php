<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance\Argument;

/**
 * The SQLite pragma's native signed 32-bit prefix conversion.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class NativePragmaInteger
{
    /**
     * Converts a native integer prefix or reports that no supported prefix fits.
     */
    public function parse(string $text): ?int
    {
        $hexadecimal = preg_match('/\A0[xX]([0-9a-fA-F]+)/', $text, $prefix) === 1;
        if (!$hexadecimal) {
            preg_match('/\A[+-]?([0-9]+)/', $text, $prefix);
        }
        if (!isset($prefix[1])) {
            return null;
        }

        $negative = str_starts_with($text, '-');
        $digits = strtolower(ltrim($prefix[1], '0'));
        $maximum = $hexadecimal ? '7fffffff' : ($negative ? '2147483648' : '2147483647');
        if (strlen($digits) > strlen($maximum) || (strlen($digits) === strlen($maximum) && strcmp($digits, $maximum) > 0)) {
            return null;
        }

        return intval(($negative ? '-' : '') . $digits, $hexadecimal ? 16 : 10);
    }
}
