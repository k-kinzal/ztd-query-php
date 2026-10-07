<?php

declare(strict_types=1);

namespace MySqlMemory\Typing;

/**
 * A character set of the server: its default collation and the bytes a character takes at most.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/charset-charsets.html.
 *
 * @visibility public
 * @example The widest character of utf8mb4
 *     \MySqlMemory\Typing\Charset::Utf8mb4->maxLength() // => 4
 */
enum Charset: string
{
    case Binary = 'binary';
    case Utf8mb4 = 'utf8mb4';
    case Utf8mb3 = 'utf8mb3';
    case Latin1 = 'latin1';
    case Ascii = 'ascii';

    /**
     * Answers the default collation of the character set.
     *
     * @example The default of utf8mb4
     *     \MySqlMemory\Typing\Charset::Utf8mb4->defaultCollation() // => \MySqlMemory\Typing\Collation::Utf8mb40900AiCi
     */
    public function defaultCollation(): Collation
    {
        return match ($this) {
            self::Binary => Collation::Binary,
            self::Utf8mb4 => Collation::Utf8mb40900AiCi,
            self::Utf8mb3 => Collation::Utf8mb3GeneralCi,
            self::Latin1 => Collation::Latin1SwedishCi,
            self::Ascii => Collation::AsciiGeneralCi,
        };
    }

    /**
     * Answers the number of bytes one character takes at most.
     *
     * @example Single-byte character sets
     *     \MySqlMemory\Typing\Charset::Latin1->maxLength() // => 1
     */
    public function maxLength(): int
    {
        return match ($this) {
            self::Utf8mb4 => 4,
            self::Utf8mb3 => 3,
            default => 1,
        };
    }

    /**
     * Counts the characters of a string of this character set.
     *
     * @example Characters, not bytes
     *     \MySqlMemory\Typing\Charset::Utf8mb4->length('猫a') // => 2
     */
    public function length(string $text): int
    {
        return $this->maxLength() === 1 ? strlen($text) : mb_strlen($text, 'UTF-8');
    }

    /**
     * Finds a character set by name, without regard to case; `utf8` names utf8mb3.
     *
     * @example The alias utf8
     *     \MySqlMemory\Typing\Charset::named('UTF8') // => \MySqlMemory\Typing\Charset::Utf8mb3
     */
    public static function named(string $name): ?self
    {
        $name = strtolower($name);

        return self::tryFrom($name === 'utf8' ? 'utf8mb3' : $name);
    }
}
