<?php

declare(strict_types=1);

namespace MySqlMemory\Value\Json;

use MySqlMemory\Value\Decimal;
use MySqlMemory\Value\Integer;
use MySqlMemory\Value\Real;

/**
 * A JSON value held as a tree, read from a JSON text or made from an SQL value.
 *
 * An integer keeps its digits, a decimal its text and a double its value; a string, a date, a
 * time, a datetime and an opaque value keep their text; an array keeps its elements and an object
 * its members, in the order the server writes them ({@see Json}). Each element and member is a
 * node of its own, so that the values a path selects are told apart by identity.
 *
 * Two values are equal as the server compares JSON values: numbers by value whatever their type,
 * strings by their bytes, arrays element by element, objects member by member, and values of
 * other types only with values of the same type.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/json.html ("Comparison and Ordering of JSON Values").
 *
 * @visibility MySqlMemory
 */
final class JsonNode
{
    /**
     * @param JsonKind $type The type of the value
     * @param bool|float|string|list<JsonNode>|array<string, JsonNode>|null $value The value: null for null, the truth of a boolean, the digits of an integer, a double, the text of a decimal, a string, a temporal value or an opaque value, the elements of an array or the members of an object
     */
    public function __construct(public readonly JsonKind $type, public readonly bool|float|string|array|null $value = null)
    {
    }

    /**
     * Reads a JSON text as the server does.
     *
     * @example An object
     *     \MySqlMemory\Value\Json\JsonNode::parse('{"b": [1, 2.5]}')->text() // => '{"b": [1, 2.5]}'
     *
     * @throws JsonSyntax When the text is not a JSON document
     */
    public static function parse(string $text): self
    {
        $at = 0;

        return self::read(Json::canonical($text), $at);
    }

    /**
     * Reads the value at a position of a text the server wrote, and moves the position past it.
     */
    public static function read(string $canonical, int &$at): self
    {
        $next = $canonical[$at];
        if ($next === '{' || $next === '[') {
            $at++;
            $members = [];
            while ($canonical[$at] !== ($next === '{' ? '}' : ']')) {
                if ($next === '{') {
                    $name = self::characters($canonical, $at);
                    $at += 2;
                    $members[$name] = self::read($canonical, $at);
                } else {
                    $members[] = self::read($canonical, $at);
                }
                if ($canonical[$at] === ',') {
                    $at += 2;
                }
            }
            $at++;

            return $next === '{' ? new self(JsonKind::Object, $members) : new self(JsonKind::Array, array_values($members));
        }
        if ($next === '"') {
            return new self(JsonKind::String, self::characters($canonical, $at));
        }
        foreach (['true' => new self(JsonKind::Boolean, true), 'false' => new self(JsonKind::Boolean, false), 'null' => new self(JsonKind::Null)] as $word => $node) {
            if (substr($canonical, $at, strlen($word)) === $word) {
                $at += strlen($word);

                return $node;
            }
        }
        $length = strspn($canonical, '-0123456789.eE+', $at);
        $number = substr($canonical, $at, $length);
        $at += $length;

        return strpbrk($number, '.eE') === false ? new self(JsonKind::Integer, $number) : new self(JsonKind::Double, (float) $number);
    }

    /**
     * Reads the characters of the string at a position of a text the server wrote, and moves the position past it.
     */
    public static function characters(string $canonical, int &$at): string
    {
        $characters = '';
        $at++;
        while (true) {
            $plain = strcspn($canonical, '"\\', $at);
            $characters .= substr($canonical, $at, $plain);
            $at += $plain;
            if ($canonical[$at] === '"') {
                $at++;

                return $characters;
            }
            $letter = $canonical[$at + 1];
            $characters .= match ($letter) {
                'b' => "\x08",
                'f' => "\x0c",
                'n' => "\n",
                'r' => "\r",
                't' => "\t",
                'u' => chr((int) hexdec(substr($canonical, $at + 2, 4))),
                default => $letter,
            };
            $at += $letter === 'u' ? 6 : 2;
        }
    }

    /**
     * Writes the value as the server writes a JSON value.
     *
     * @example A string with a quotation mark
     *     (new \MySqlMemory\Value\Json\JsonNode(\MySqlMemory\Value\Json\JsonKind::String, 'a"b'))->text() // => '"a\"b"'
     */
    public function text(): string
    {
        $value = $this->value;

        return match ($this->type) {
            JsonKind::Null => 'null',
            JsonKind::Boolean => $value === true ? 'true' : 'false',
            JsonKind::Integer, JsonKind::Decimal => $this->scalar(),
            JsonKind::Double => Json::double(is_float($value) ? $value : 0.0),
            JsonKind::String, JsonKind::Date, JsonKind::Time, JsonKind::DateTime, JsonKind::Opaque => Json::quote($this->scalar()),
            JsonKind::Array => '[' . implode(', ', array_map(static fn (self $element): string => $element->text(), $this->children())) . ']',
            JsonKind::Object => '{' . implode(', ', array_map(static fn (string $name, self $member): string => Json::quote($name) . ': ' . $member->text(), array_map('strval', array_keys(is_array($value) ? $value : [])), $this->children())) . '}',
        };
    }

    /**
     * Answers the text an integer, a decimal, a string, a temporal value or an opaque value keeps; empty for another value.
     *
     * @example A decimal
     *     (new \MySqlMemory\Value\Json\JsonNode(\MySqlMemory\Value\Json\JsonKind::Decimal, '1.50'))->scalar() // => '1.50'
     */
    public function scalar(): string
    {
        return is_string($this->value) ? $this->value : '';
    }

    /**
     * Writes the value as JSON_UNQUOTE() does: the characters of a string, else the text of the value.
     *
     * @example A string
     *     (new \MySqlMemory\Value\Json\JsonNode(\MySqlMemory\Value\Json\JsonKind::String, 'a"b'))->unquoted() // => 'a"b'
     */
    public function unquoted(): string
    {
        return $this->type === JsonKind::String ? $this->scalar() : $this->text();
    }

    /**
     * Answers the elements of an array or the members of an object in order, and nothing for another value.
     *
     * @return list<JsonNode>
     */
    public function children(): array
    {
        $children = [];
        foreach (is_array($this->value) ? $this->value : [] as $child) {
            $children[] = $child;
        }

        return $children;
    }

    /**
     * Answers the value and every value it holds, each before the values it holds, in order.
     *
     * @return list<JsonNode>
     */
    public function descendants(): array
    {
        $found = [$this];
        foreach ($this->children() as $child) {
            array_push($found, ...$child->descendants());
        }

        return $found;
    }

    /**
     * Tells whether two values are equal as the server compares JSON values.
     *
     * @example An integer and a double
     *     (new \MySqlMemory\Value\Json\JsonNode(\MySqlMemory\Value\Json\JsonKind::Integer, '1'))->equals(new \MySqlMemory\Value\Json\JsonNode(\MySqlMemory\Value\Json\JsonKind::Double, 1.0)) // => true
     */
    public function equals(self $other): bool
    {
        if ($this->type->numeric() && $other->type->numeric()) {
            return self::number($this) === self::number($other);
        }
        if ($this->type !== $other->type) {
            return false;
        }
        if ($this->type === JsonKind::Array || $this->type === JsonKind::Object) {
            $mine = is_array($this->value) ? $this->value : [];
            $theirs = is_array($other->value) ? $other->value : [];
            if (array_keys($mine) !== array_keys($theirs)) {
                return false;
            }
            foreach ($mine as $key => $child) {
                if (!$child->equals($theirs[$key])) {
                    return false;
                }
            }

            return true;
        }

        return $this->value === $other->value;
    }

    /**
     * Writes a number in positional notation without trailing zeros, so that equal numbers of any type are written alike; a double is written with the shortest digits that read back as it.
     *
     * @example A double
     *     \MySqlMemory\Value\Json\JsonNode::number(new \MySqlMemory\Value\Json\JsonNode(\MySqlMemory\Value\Json\JsonKind::Double, 1.5e2)) // => '150'
     */
    public static function number(self $node): string
    {
        $value = $node->value;
        $text = $node->scalar();
        if (is_float($value)) {
            [$digits, $point] = Real::digits(abs($value));
            $length = strlen($digits);
            $text = match (true) {
                $point <= 0 => '0.' . str_repeat('0', -$point) . $digits,
                $point >= $length => $digits . str_repeat('0', $point - $length),
                default => substr($digits, 0, $point) . '.' . substr($digits, $point),
            };
            $text = ($value < 0 ? '-' : '') . $text;
        }
        if (str_contains($text, '.')) {
            $text = rtrim(rtrim($text, '0'), '.');
        }

        return Decimal::canonical($text);
    }
}
