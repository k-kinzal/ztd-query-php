<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Tokenization;

/**
 * Classifies unsigned digit runs without narrowing them to a PHP integer.
 *
 * @visibility root
 */
final class IntegerToken
{
    /**
     * Selects the narrowest token whose upper bound contains the decimal magnitude.
     */
    public static function of(string $integer): string
    {
        $normalized = ltrim($integer, '0');
        $normalized = $normalized === '' ? '0' : $normalized;

        foreach ([['2147483647', 'NUM'], ['9223372036854775807', 'LONG_NUM'], ['18446744073709551615', 'ULONGLONG_NUM']] as [$maximum, $token]) {
            if (strlen($normalized) < strlen($maximum) || (strlen($normalized) === strlen($maximum) && strcmp($normalized, $maximum) <= 0)) {
                return $token;
            }
        }

        return 'DECIMAL_NUM';
    }
}
