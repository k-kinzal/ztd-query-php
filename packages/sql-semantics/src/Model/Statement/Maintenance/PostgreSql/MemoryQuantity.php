<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Maintenance\PostgreSql;

/**
 * Reads a memory quantity in kilobytes as the server's integer parameter parser does.
 * @visibility SqlSemantics
 */
final class MemoryQuantity
{
    /**
     * Accepts decimal, octal and hexadecimal integers or decimal fractions, then an optional case-sensitive unit; returns null when unreadable.
     */
    public static function kilobytes(string $text): ?int
    {
        if (preg_match('/^\s*([+-]?(?:\d+\.\d*|\.\d+|\d+)(?:[eE][+-]?\d+)?|[+-]?0[xX][0-9a-fA-F]+)\s*(B|kB|MB|GB|TB)?\s*$/D', $text, $match) !== 1) {
            return null;
        }
        $number = $match[1];
        $digits = ltrim($number, '+-');
        $value = match (true) {
            str_starts_with(strtolower($digits), '0x') => (float) hexdec(substr($digits, 2)),
            preg_match('/^0[0-7]+$/D', $digits) === 1 => (float) octdec($digits),
            preg_match('/^0\d+$/D', $digits) === 1 => null,
            default => (float) $digits,
        };
        if ($value === null) {
            return null;
        }
        $value = str_starts_with($number, '-') ? -$value : $value;
        $scaled = round(match ($match[2] ?? '') {
            'B' => $value / 1024,
            'MB' => $value * 1024,
            'GB' => $value * 1048576,
            'TB' => $value * 1073741824,
            'kB', '' => $value,
        }, 0, PHP_ROUND_HALF_EVEN);
        return $scaled > 2147483647 || $scaled < -2147483648 ? null : (int) $scaled;
    }
}
