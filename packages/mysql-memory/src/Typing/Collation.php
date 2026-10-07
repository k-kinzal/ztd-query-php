<?php

declare(strict_types=1);

namespace MySqlMemory\Typing;

/**
 * A collation the emulator compares strings with: its number, character set, padding and order.
 *
 * Strings are compared by the Unicode Collation Algorithm for the `_0900_` collations (the
 * accent- and case-insensitive `ai_ci` at the primary level, `as_cs` at the tertiary level), by
 * code point for `utf8mb4_bin`, by simple case folding for `general_ci`, and by byte for
 * `binary`. A PAD SPACE collation ignores trailing spaces; the `_0900_` collations and `binary`
 * are NO PAD.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/charset-unicode-sets.html.
 *
 * @visibility public
 * @example Comparing without regard to accents and case
 *     \MySqlMemory\Typing\Collation::Utf8mb40900AiCi->compare('Élan', 'elan') // => 0
 * @example Comparing bytes
 *     \MySqlMemory\Typing\Collation::Binary->compare('a', 'A') // => 1
 */
enum Collation: string
{
    case Binary = 'binary';
    case Utf8mb40900AiCi = 'utf8mb4_0900_ai_ci';
    case Utf8mb40900AsCs = 'utf8mb4_0900_as_cs';
    case Utf8mb40900AsCi = 'utf8mb4_0900_as_ci';
    case Utf8mb40900Bin = 'utf8mb4_0900_bin';
    case Utf8mb4Bin = 'utf8mb4_bin';
    case Utf8mb4GeneralCi = 'utf8mb4_general_ci';
    case Utf8mb4UnicodeCi = 'utf8mb4_unicode_ci';
    case Utf8mb3GeneralCi = 'utf8mb3_general_ci';
    case Utf8mb3Bin = 'utf8mb3_bin';
    case Latin1SwedishCi = 'latin1_swedish_ci';
    case Latin1Bin = 'latin1_bin';
    case AsciiGeneralCi = 'ascii_general_ci';
    case AsciiBin = 'ascii_bin';

    /**
     * Answers the collation number the protocol sends.
     *
     * @example The default collation of MySQL 8
     *     \MySqlMemory\Typing\Collation::Utf8mb40900AiCi->id() // => 255
     */
    public function id(): int
    {
        return match ($this) {
            self::Binary => 63,
            self::Utf8mb40900AiCi => 255,
            self::Utf8mb40900AsCs => 278,
            self::Utf8mb40900AsCi => 305,
            self::Utf8mb40900Bin => 309,
            self::Utf8mb4Bin => 46,
            self::Utf8mb4GeneralCi => 45,
            self::Utf8mb4UnicodeCi => 224,
            self::Utf8mb3GeneralCi => 33,
            self::Utf8mb3Bin => 83,
            self::Latin1SwedishCi => 8,
            self::Latin1Bin => 47,
            self::AsciiGeneralCi => 11,
            self::AsciiBin => 65,
        };
    }

    /**
     * Answers the character set of the collation.
     *
     * @example A utf8mb4 collation
     *     \MySqlMemory\Typing\Collation::Utf8mb4Bin->charset() // => \MySqlMemory\Typing\Charset::Utf8mb4
     */
    public function charset(): Charset
    {
        return match ($this) {
            self::Binary => Charset::Binary,
            self::Utf8mb3GeneralCi, self::Utf8mb3Bin => Charset::Utf8mb3,
            self::Latin1SwedishCi, self::Latin1Bin => Charset::Latin1,
            self::AsciiGeneralCi, self::AsciiBin => Charset::Ascii,
            default => Charset::Utf8mb4,
        };
    }

    /**
     * Tells whether trailing spaces are ignored in comparisons.
     *
     * @example The NO PAD collations of MySQL 8
     *     [\MySqlMemory\Typing\Collation::Utf8mb40900AiCi->padSpace(), \MySqlMemory\Typing\Collation::Utf8mb4Bin->padSpace()] // => [false, true]
     */
    public function padSpace(): bool
    {
        return !in_array($this, [self::Binary, self::Utf8mb40900AiCi, self::Utf8mb40900AsCs, self::Utf8mb40900AsCi, self::Utf8mb40900Bin], true);
    }

    /**
     * Tells whether the collation compares bytes or code points without folding.
     *
     * @example Binary collations
     *     [\MySqlMemory\Typing\Collation::Utf8mb4Bin->binary(), \MySqlMemory\Typing\Collation::Utf8mb40900AiCi->binary()] // => [true, false]
     */
    public function binary(): bool
    {
        return in_array($this, [self::Binary, self::Utf8mb4Bin, self::Utf8mb40900Bin, self::Utf8mb3Bin, self::Latin1Bin, self::AsciiBin], true);
    }

    /**
     * Compares two strings: negative, zero or positive.
     *
     * @example Trailing spaces under a PAD SPACE collation
     *     \MySqlMemory\Typing\Collation::Utf8mb4GeneralCi->compare('a ', 'A') // => 0
     */
    public function compare(string $left, string $right): int
    {
        return Ordering::of($this)->compare($left, $right);
    }

    /**
     * Answers a key that is equal for two strings exactly when they compare equal.
     *
     * @example Equal keys of equal strings
     *     \MySqlMemory\Typing\Collation::Utf8mb40900AiCi->key('A') === \MySqlMemory\Typing\Collation::Utf8mb40900AiCi->key('á') // => true
     */
    public function key(string $text): string
    {
        return Ordering::of($this)->key($text);
    }

    /**
     * Finds a collation by name, without regard to case.
     *
     * @example A name written in upper case
     *     \MySqlMemory\Typing\Collation::named('UTF8MB4_BIN') // => \MySqlMemory\Typing\Collation::Utf8mb4Bin
     */
    public static function named(string $name): ?self
    {
        $name = strtolower($name);

        return self::tryFrom(str_starts_with($name, 'utf8_') ? 'utf8mb3_' . substr($name, 5) : $name);
    }
}
