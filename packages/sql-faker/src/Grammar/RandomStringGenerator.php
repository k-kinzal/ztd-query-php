<?php

declare(strict_types=1);

namespace SqlFaker\Grammar;

use SqlFaker\Contract\RandomSource;

/**
 * Generates random strings for SQL token production.
 *
 * Shared by all SQL generators and providers to produce identifiers,
 * string literals, numeric literals, and other lexical tokens.
 */
final class RandomStringGenerator
{
    private const INITIAL_IDENTIFIER_CHARS = '_';
    private const ALNUM_UNDERSCORE = 'abcdefghijklmnopqrstuvwxyz0123456789_';
    private const MIXED_ALNUM_UNDERSCORE = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_';
    private const HOSTNAME_INITIAL_CHARS = 'abcdefghijklmnopqrstuvwxyz';
    private const HOSTNAME_CHARS = 'abcdefghijklmnopqrstuvwxyz0123456789';
    private const HEX_CHARS = '0123456789abcdef';
    private const DIGIT_CHARS = '0123456789';
    private const BINARY_CHARS = '01';

    public function __construct(
        private readonly RandomSource $random,
    ) {
    }

    /**
     * Generate a raw SQL identifier (starts with letter or underscore, followed by alphanumeric/underscore).
     */
    public function rawIdentifier(int $minLength = 1, int $maxLength = 12): string
    {
        $length = $this->random->numberBetween(max($minLength, 1), $maxLength);

        return $this->randomChar(self::INITIAL_IDENTIFIER_CHARS)
            . $this->randomString(self::ALNUM_UNDERSCORE, $length - 1);
    }

    /**
     * Generate a canonical fresh identifier for statement-local rendering.
     */
    public function canonicalIdentifier(int $ordinal): string
    {
        return '_i' . base_convert((string) max($ordinal, 0), 10, 36);
    }

    /**
     * Generate a random string from the mixed alphanumeric+underscore alphabet.
     */
    public function mixedAlnumString(int $minLength = 1, int $maxLength = 24): string
    {
        return $this->randomString(self::MIXED_ALNUM_UNDERSCORE, $this->random->numberBetween($minLength, $maxLength));
    }

    /**
     * Generate a random integer literal string.
     */
    public function integerString(int $min = 1, int $max = 9999999999): string
    {
        return (string) $this->random->numberBetween($min, $max);
    }

    /**
     * Generate a random hexadecimal string.
     */
    public function hexString(int $minLength = 1, int $maxLength = 16): string
    {
        return $this->randomString(self::HEX_CHARS, $this->random->numberBetween($minLength, $maxLength));
    }

    /**
     * Generate a random binary string (0s and 1s).
     */
    public function binaryString(int $minLength = 1, int $maxLength = 32): string
    {
        return $this->randomString(self::BINARY_CHARS, $this->random->numberBetween($minLength, $maxLength));
    }

    /**
     * Generate a random decimal literal string.
     */
    public function decimalString(int $precision = 13, int $scale = 6): string
    {
        $intDigits = max($precision - $scale, 1);
        $maxIntPart = (int) str_repeat('9', $intDigits);
        $maxFracPart = (int) str_repeat('9', max($scale, 2));

        $intPart = $this->random->numberBetween(0, $maxIntPart);
        $fracPart = $this->random->numberBetween(0, $maxFracPart);

        return $intPart . '.' . str_pad((string) $fracPart, max($scale, 2), '0', STR_PAD_LEFT);
    }

    /**
     * Generate a random hostname string.
     */
    public function hostnameString(int $minParts = 1, int $maxParts = 3, int $minPartLength = 1, int $maxPartLength = 12): string
    {
        $parts = $this->random->numberBetween($minParts, $maxParts);
        $segments = [];
        for ($p = 0; $p < $parts; $p++) {
            $partLength = $this->random->numberBetween($minPartLength, $maxPartLength);
            $segments[] = $this->randomChar(self::HOSTNAME_INITIAL_CHARS)
                . $this->randomString(self::HOSTNAME_CHARS, $partLength - 1);
        }

        return implode('.', $segments);
    }

    /**
     * Generate a random unsigned big integer string.
     */
    public function unsignedBigIntString(int $minLength = 1, int $maxLength = 20): string
    {
        $buf = $this->randomString(self::DIGIT_CHARS, $this->random->numberBetween($minLength, $maxLength));
        $trimmed = ltrim($buf, '0');

        return $trimmed === '' ? '0' : $trimmed;
    }

    /**
     * Generate a random long integer string.
     */
    public function longIntString(int $min = 0, int $max = 2147483647): string
    {
        $val = $this->random->numberBetween($min, $max);

        return "{$val}";
    }

    /**
     * Generate a random float literal string with exponent.
     */
    public function floatString(string $mantissa, int $minExponent = -20, int $maxExponent = 20): string
    {
        return $mantissa . 'e' . $this->random->numberBetween($minExponent, $maxExponent);
    }

    /**
     * Generate a random parameter index string.
     */
    public function parameterIndex(int $min = 1, int $max = 10): string
    {
        $val = $this->random->numberBetween($min, $max);

        return "{$val}";
    }

    /**
     * Generate a random string of given length from an alphabet.
     */
    private function randomString(string $alphabet, int $length): string
    {
        $buf = '';
        for ($i = 0; $i < $length; $i++) {
            $buf .= $this->randomChar($alphabet);
        }
        return $buf;
    }

    /**
     * Pick a random character from an alphabet string.
     */
    private function randomChar(string $alphabet): string
    {
        return $alphabet[$this->random->numberBetween(0, strlen($alphabet) - 1)];
    }
}
