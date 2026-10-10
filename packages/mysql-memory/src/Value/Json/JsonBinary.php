<?php

declare(strict_types=1);

namespace MySqlMemory\Value\Json;

use MySqlMemory\Value\Decimal;

/**
 * Measures the binary form the server stores a JSON value in, as JSON_STORAGE_SIZE() reports it.
 *
 * A document is one byte for the type of its top value, then the value. An array or an object is
 * small when it fits in 65535 bytes, else large; a small one counts its elements and its size in
 * two bytes each and points at its values with two-byte offsets, a large one uses four bytes for
 * each. An array has an entry of a type byte and an offset for each element; an object has an
 * entry of an offset and a two-byte length for each name and an entry of a type byte and an offset
 * for each value, then its names. Each array or object nested in another chooses its own form.
 * A null, a boolean, and an integer that fits 16 bits (or 32 bits in a large array or object) is
 * held in its entry; any other value follows the entries. At the top, a null or a boolean takes one
 * byte, and an integer two, four or eight bytes, the fewest its value fits in. A double takes eight
 * bytes. A string is its length, written seven bits to a byte with the high bit telling that more
 * bytes follow, and its utf8mb4 bytes. A temporal value takes a byte for its SQL type, a byte for
 * its length and eight bytes. An opaque value takes a byte for its SQL type, its length written as
 * a string's, and its bytes. A decimal is an opaque value of a byte for its precision, a byte for
 * its scale and its digits in the packed form of a DECIMAL column, four bytes for each nine digits
 * and one to four bytes for the rest; its integer digits are counted in whole groups of nine, none
 * when its integer part is zero, and a decimal without digits takes one byte (verified on a live 8.4
 * server: a decimal that is the result of an operation or a cast, or that is read from a column
 * whose integer digits are a multiple of nine or that is DECIMAL(10,2), is counted so; the server
 * counts a decimal literal and a value of another column by the digits it has).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/json-utility-functions.html#function_json-storage-size,
 * https://dev.mysql.com/doc/refman/8.4/en/storage-requirements.html ("JSON Storage Requirements"),
 * https://dev.mysql.com/doc/refman/8.4/en/precision-math-decimal-characteristics.html.
 *
 * @visibility MySqlMemory
 */
final class JsonBinary
{
    /**
     * The largest size of a small array or object, in bytes.
     */
    public const SMALL = 65535;

    /**
     * Answers the number of bytes of the binary form of a document.
     *
     * @example An array of an integer
     *     \MySqlMemory\Value\Json\JsonBinary::size(\MySqlMemory\Value\Json\JsonNode::parse('[1]')) // => 8
     */
    public static function size(JsonNode $node): int
    {
        return 1 + match ($node->type) {
            JsonKind::Null, JsonKind::Boolean => 1,
            JsonKind::Integer, JsonKind::Unsigned => self::integer($node),
            JsonKind::Double, JsonKind::Decimal, JsonKind::String, JsonKind::Array, JsonKind::Object, JsonKind::Date, JsonKind::Time, JsonKind::DateTime, JsonKind::Timestamp, JsonKind::Opaque => self::value($node),
        };
    }

    /**
     * Answers the number of bytes a value takes after the entries of the array or object that holds it, without its type byte.
     *
     * @example A string
     *     \MySqlMemory\Value\Json\JsonBinary::value(new \MySqlMemory\Value\Json\JsonNode(\MySqlMemory\Value\Json\JsonKind::String, 'ab')) // => 3
     */
    public static function value(JsonNode $node): int
    {
        return match ($node->type) {
            JsonKind::Null, JsonKind::Boolean => 0,
            JsonKind::Integer, JsonKind::Unsigned => self::integer($node),
            JsonKind::Double => 8,
            JsonKind::Decimal => self::opaque(2 + self::decimal($node->scalar())),
            JsonKind::String => self::length(strlen($node->scalar())) + strlen($node->scalar()),
            JsonKind::Date, JsonKind::Time, JsonKind::DateTime, JsonKind::Timestamp => self::opaque(8),
            JsonKind::Opaque => self::opaque(strlen((string) base64_decode(str_replace("\n", '', substr($node->scalar(), (int) strpos($node->scalar(), ':', 7) + 1)), true))),
            JsonKind::Array, JsonKind::Object => self::container($node),
        };
    }

    /**
     * Answers the number of bytes of an array or an object: its small form when it fits, else its large form.
     *
     * @example An empty object
     *     \MySqlMemory\Value\Json\JsonBinary::container(\MySqlMemory\Value\Json\JsonNode::parse('{}')) // => 4
     */
    public static function container(JsonNode $node): int
    {
        $small = self::form($node, false);

        return $small <= self::SMALL ? $small : self::form($node, true);
    }

    /**
     * Answers the number of bytes of an array or an object in its small or its large form.
     *
     * @example A large array of a string
     *     \MySqlMemory\Value\Json\JsonBinary::form(\MySqlMemory\Value\Json\JsonNode::parse('["a"]'), true) // => 15
     */
    public static function form(JsonNode $node, bool $large): int
    {
        $offset = $large ? 4 : 2;
        $children = $node->children();
        $size = 2 * $offset + count($children) * (1 + $offset);
        if ($node->type === JsonKind::Object && is_array($node->value)) {
            foreach (array_keys($node->value) as $name) {
                $size += $offset + 2 + strlen((string) $name);
            }
        }
        foreach ($children as $child) {
            if (!self::inlined($child, $large)) {
                $size += self::value($child);
            }
        }

        return $size;
    }

    /**
     * Tells whether a value is held in its entry of a small or a large array or object.
     *
     * @example An integer of 32 bits in a large array
     *     \MySqlMemory\Value\Json\JsonBinary::inlined(\MySqlMemory\Value\Json\JsonNode::parse('65536'), true) // => true
     */
    public static function inlined(JsonNode $node, bool $large): bool
    {
        return match ($node->type) {
            JsonKind::Null, JsonKind::Boolean => true,
            JsonKind::Integer, JsonKind::Unsigned => self::integer($node) <= ($large ? 4 : 2),
            JsonKind::Double, JsonKind::Decimal, JsonKind::String, JsonKind::Array, JsonKind::Object, JsonKind::Date, JsonKind::Time, JsonKind::DateTime, JsonKind::Timestamp, JsonKind::Opaque => false,
        };
    }

    /**
     * Answers the number of bytes of an integer: two, four or eight, the fewest its value fits in as a signed or an unsigned integer.
     *
     * @example A signed integer above 16 bits
     *     \MySqlMemory\Value\Json\JsonBinary::integer(\MySqlMemory\Value\Json\JsonNode::parse('32768')) // => 4
     */
    public static function integer(JsonNode $node): int
    {
        $digits = $node->scalar();
        [$small, $medium] = $node->type === JsonKind::Unsigned ? [['0', '65535'], ['0', '4294967295']] : [['-32768', '32767'], ['-2147483648', '2147483647']];
        foreach ([2 => $small, 4 => $medium] as $bytes => [$lowest, $highest]) {
            if (Decimal::compare($digits, $lowest) >= 0 && Decimal::compare($digits, $highest) <= 0) {
                return $bytes;
            }
        }

        return 8;
    }

    /**
     * Answers the number of bytes of an opaque value of a number of bytes: its SQL type, its length and its bytes.
     *
     * @example A value of 200 bytes
     *     \MySqlMemory\Value\Json\JsonBinary::opaque(200) // => 203
     */
    public static function opaque(int $bytes): int
    {
        return 1 + self::length($bytes) + $bytes;
    }

    /**
     * Answers the number of bytes a length is written in: seven bits to a byte.
     *
     * @example A length of 128
     *     \MySqlMemory\Value\Json\JsonBinary::length(128) // => 2
     */
    public static function length(int $length): int
    {
        $bytes = 1;
        while ($length >= 128) {
            $length >>= 7;
            $bytes++;
        }

        return $bytes;
    }

    /**
     * Answers the number of bytes of the packed digits of a decimal written in text.
     *
     * @example A decimal with an integer part
     *     \MySqlMemory\Value\Json\JsonBinary::decimal('-12.50') // => 5
     */
    public static function decimal(string $text): int
    {
        [$integer, $fraction] = array_pad(explode('.', ltrim($text, '+-')), 2, '');
        $digits = strlen(ltrim($integer, '0'));
        $words = intdiv($digits + 8, 9);
        $packed = self::digits($words * 9) + self::digits(strlen($fraction));

        return max($packed, 1);
    }

    /**
     * Answers the number of bytes a run of decimal digits is packed in: four for each nine, and one to four for the rest.
     *
     * @example Ten digits
     *     \MySqlMemory\Value\Json\JsonBinary::digits(10) // => 5
     */
    public static function digits(int $digits): int
    {
        return intdiv($digits, 9) * 4 + [0, 1, 1, 2, 2, 3, 3, 4, 4][$digits % 9];
    }
}
