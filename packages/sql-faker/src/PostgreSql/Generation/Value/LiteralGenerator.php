<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql\Generation\Value;

use Faker\Generator as FakerGenerator;
use SqlFaker\Generation\Value\RandomCharacters;

/**
 * Generates PostgreSql literal bodies for the bounded lexical API.
 *
 * Owns the alphabets and numeric formatting used by this dialect.
 *
 * @visibility root
 */
final class LiteralGenerator
{
    private const ALPHA_UNDERSCORE = 'abcdefghijklmnopqrstuvwxyz_';
    private const ALNUM_UNDERSCORE = 'abcdefghijklmnopqrstuvwxyz0123456789_';
    private const MIXED_ALNUM_UNDERSCORE = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_';
    private const HEX_CHARS = '0123456789abcdef';
    private const BINARY_CHARS = '01';

    private FakerGenerator $faker;

    private RandomCharacters $characters;

    /**
     * @param FakerGenerator $faker Source of every choice the generated strings make
     */
    public function __construct(FakerGenerator $faker)
    {
        $this->faker = $faker;
        $this->characters = new RandomCharacters($faker);
    }

    /**
     * Generate a raw SQL identifier (starts with letter or underscore, followed by alphanumeric/underscore).
     */
    public function rawIdentifier(int $minLength = 1, int $maxLength = 12): string
    {
        $length = $this->faker->numberBetween(max($minLength, 1), $maxLength);

        return $this->characters->character(self::ALPHA_UNDERSCORE)
            . $this->characters->string(self::ALNUM_UNDERSCORE, $length - 1);
    }

    /**
     * Generate a random string from the mixed alphanumeric+underscore alphabet.
     */
    public function mixedAlnumString(int $minLength = 1, int $maxLength = 24): string
    {
        return $this->characters->string(self::MIXED_ALNUM_UNDERSCORE, $this->faker->numberBetween($minLength, $maxLength));
    }

    /**
     * Generate a random integer literal string.
     *
     * @return non-empty-string
     */
    public function integerString(int $min = 1, int $max = 9999999999): string
    {
        return (string) $this->faker->numberBetween($min, $max);
    }

    /**
     * Generate a random hexadecimal string.
     */
    public function hexString(int $minLength = 1, int $maxLength = 16): string
    {
        return $this->characters->string(self::HEX_CHARS, $this->faker->numberBetween($minLength, $maxLength));
    }

    /**
     * Generate a random binary string (0s and 1s).
     */
    public function binaryString(int $minLength = 1, int $maxLength = 32): string
    {
        return $this->characters->string(self::BINARY_CHARS, $this->faker->numberBetween($minLength, $maxLength));
    }

    /**
     * Generate a random decimal literal string.
     *
     * @return non-empty-string
     */
    public function decimalString(int $precision = 13, int $scale = 6): string
    {
        $intDigits = max($precision - $scale, 1);
        $maxIntPart = (int) str_repeat('9', $intDigits);
        $maxFracPart = (int) str_repeat('9', max($scale, 2));

        $intPart = $this->faker->numberBetween(0, $maxIntPart);
        $fracPart = $this->faker->numberBetween(0, $maxFracPart);

        return $intPart . '.' . str_pad((string) $fracPart, max($scale, 2), '0', STR_PAD_LEFT);
    }







    /**
     * Generate a random float literal string with exponent.
     *
     * @return non-empty-string
     */
    public function floatString(string $mantissa, int $minExponent = -20, int $maxExponent = 20): string
    {
        return $mantissa . 'e' . $this->faker->numberBetween($minExponent, $maxExponent);
    }

    /**
     * Generate a random parameter index string.
     *
     * @return non-empty-string
     */
    public function parameterIndex(int $min = 1, int $max = 10): string
    {
        $val = $this->faker->numberBetween($min, $max);

        return "{$val}";
    }



}
