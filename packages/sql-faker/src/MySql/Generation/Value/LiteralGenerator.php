<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Value;

use Faker\Generator as FakerGenerator;
use LogicException;
use SqlFaker\Generation\Value\RandomCharacters;

/**
 * Generates MySql literal bodies for the bounded lexical API.
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
    private const HOSTNAME_CHARS = 'abcdefghijklmnopqrstuvwxyz0123456789';
    private const HEX_CHARS = '0123456789abcdef';
    private const DIGIT_CHARS = '0123456789';
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
     * Generate a random hostname string.
     *
     * @return non-empty-string
     *
     * @throws LogicException When the requested bounds leave every part of the hostname empty
     */
    public function hostnameString(int $minParts = 1, int $maxParts = 3, int $minPartLength = 1, int $maxPartLength = 12): string
    {
        $parts = $this->faker->numberBetween($minParts, $maxParts);
        $segments = [];
        for ($p = 0; $p < $parts; $p++) {
            $segments[] = $this->characters->string(self::HOSTNAME_CHARS, $this->faker->numberBetween($minPartLength, $maxPartLength));
        }

        $hostname = implode('.', $segments);
        if ($hostname === '') {
            throw new LogicException('Hostname generation produced an empty result.');
        }

        return $hostname;
    }

    /**
     * Generate a random unsigned big integer string.
     *
     * @return non-empty-string
     */
    public function unsignedBigIntString(int $minLength = 1, int $maxLength = 20): string
    {
        $buf = $this->characters->string(self::DIGIT_CHARS, $this->faker->numberBetween($minLength, $maxLength));
        $trimmed = ltrim($buf, '0');

        return $trimmed === '' ? '0' : $trimmed;
    }

    /**
     * Generate a random long integer string.
     *
     * @return non-empty-string
     */
    public function longIntString(int $min = 0, int $max = 2147483647): string
    {
        $val = $this->faker->numberBetween($min, $max);

        return "{$val}";
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





}
