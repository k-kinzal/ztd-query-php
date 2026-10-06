<?php

declare(strict_types=1);

namespace Deriver\Value;

/**
 * Classifies numeric strings without rounding integer boundary digits through a float.
 * @visibility root
 */
final class NumericString
{
    /**
     * Parses the numeric category used by PHP scalar arithmetic and union coercion.
     * @param string $value Candidate numeric string
     * @return int|float|null Target numeric value, or null for a nonnumeric string
     */
    public function parse(string $value): int|float|null
    {
        if (!is_numeric($value)) {
            return null;
        }
        return $this->integer($value) ?? (float) $value;
    }

    /**
     * Checks a signed decimal integer against the 64-bit target before conversion.
     * @param string $value Candidate integer string
     * @return int|null Exact target integer, or null for float syntax or overflow
     */
    public function integer(string $value): ?int
    {
        if (preg_match('/^[\x20\t\r\n\v\f]*([+-]?)([0-9]+)[\x20\t\r\n\v\f]*$/D', $value, $parts) !== 1) {
            return null;
        }
        $digits = ltrim($parts[2], '0');
        $limit = $parts[1] === '-' ? '9223372036854775808' : '9223372036854775807';
        if (strlen($digits) > strlen($limit) || strlen($digits) === strlen($limit) && strcmp($digits, $limit) > 0) {
            return null;
        }
        return (int) $value;
    }
}
