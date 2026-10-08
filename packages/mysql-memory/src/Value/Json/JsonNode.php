<?php

declare(strict_types=1);

namespace MySqlMemory\Value\Json;

use MySqlMemory\Value\Decimal;
use MySqlMemory\Value\Order;
use MySqlMemory\Value\Real;

/**
 * A JSON value held as a tree, read from a JSON text or made from an SQL value.
 *
 * An integer keeps its digits, a decimal its text and a double its value; a string, a date, a
 * time, a datetime and an opaque value keep their text; an array keeps its elements and an object
 * its members, in the order the server writes them ({@see Json}). Each element and member is a
 * node of its own, so that the values a path selects are told apart by identity.
 *
 * A JSON value of an SQL expression is held as its JSON text. A document that holds a value whose
 * type the text does not tell (a decimal, an unsigned integer, a temporal or an opaque value) is
 * held as its text, a NUL byte and its typed text, where each such value is written after a
 * backquote and the letter of its type ({@see JsonKind::mark()}), so that the value keeps its
 * type when it is stored and read again, as it does in the binary format of the server.
 *
 * Values of different types order by the rank of their types ({@see JsonKind::rank()}). Numbers
 * compare by value whatever their type, strings and opaque values by their bytes, booleans false
 * first, temporal values by time, arrays element by element and then by length, and objects by
 * their number of members and then member by member in the order they are written, each by its
 * name and then its value (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/json.html ("Comparison and Ordering of JSON Values").
 *
 * @visibility MySqlMemory
 */
final class JsonNode
{
    /**
     * @param JsonKind $type The type of the value
     * @param bool|float|string|array<int|string, JsonNode>|null $value The value: null for null, the truth of a boolean, the digits of an integer, a double, the text of a decimal, a string, a temporal value or an opaque value, the elements of an array or the members of an object
     */
    public function __construct(public readonly JsonKind $type, public readonly bool|float|string|array|null $value = null)
    {
    }

    /**
     * Reads a JSON text as the server does, refusing one that nests deeper than a limit, 100 by default.
     *
     * @example An object
     *     \MySqlMemory\Value\Json\JsonNode::parse('{"b": [1, 2.5]}')->text() // => '{"b": [1, 2.5]}'
     *
     * @throws JsonSyntax When the text is not a JSON document
     */
    public static function parse(string $text, int $limit = Json::MAX_DEPTH): self
    {
        $at = 0;

        return JsonReader::read(Json::canonical($text, $limit), $at);
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
            JsonKind::Integer, JsonKind::Unsigned, JsonKind::Decimal => $this->scalar(),
            JsonKind::Double => Json::double(is_float($value) ? $value : 0.0),
            JsonKind::String, JsonKind::Date, JsonKind::Time, JsonKind::DateTime, JsonKind::Timestamp, JsonKind::Opaque => Json::quote($this->scalar()),
            JsonKind::Array => '[' . implode(', ', array_map(static fn (self $element): string => $element->text(), $this->children())) . ']',
            JsonKind::Object => '{' . implode(', ', array_map(static fn (string $name, self $member): string => Json::quote($name) . ': ' . $member->text(), array_map('strval', array_keys(is_array($value) ? $value : [])), $this->children())) . '}',
        };
    }

    /**
     * Writes the value in the typed text of a document: its JSON text, with each value whose type the text does not tell marked with a backquote and the letter of its type.
     *
     * @example A decimal in an array
     *     (new \MySqlMemory\Value\Json\JsonNode(\MySqlMemory\Value\Json\JsonKind::Array, [new \MySqlMemory\Value\Json\JsonNode(\MySqlMemory\Value\Json\JsonKind::Decimal, '1.50')]))->typed() // => '[`d1.50]'
     */
    public function typed(): string
    {
        $mark = $this->type->mark();
        if ($mark !== '') {
            return '`' . $mark . $this->text();
        }
        $value = $this->value;

        return match ($this->type) {
            JsonKind::Array => '[' . implode(', ', array_map(static fn (self $element): string => $element->typed(), $this->children())) . ']',
            JsonKind::Object => '{' . implode(', ', array_map(static fn (string $name, self $member): string => Json::quote($name) . ': ' . $member->typed(), array_map('strval', array_keys(is_array($value) ? $value : [])), $this->children())) . '}',
            JsonKind::Null, JsonKind::Boolean, JsonKind::Integer, JsonKind::Unsigned, JsonKind::Double, JsonKind::Decimal, JsonKind::String, JsonKind::Date, JsonKind::Time, JsonKind::DateTime, JsonKind::Timestamp, JsonKind::Opaque => $this->text(),
        };
    }

    /**
     * Tells whether the value holds a value whose type its JSON text does not tell.
     *
     * @example A date
     *     (new \MySqlMemory\Value\Json\JsonNode(\MySqlMemory\Value\Json\JsonKind::Date, '2024-01-31'))->marked() // => true
     */
    public function marked(): bool
    {
        if ($this->type->mark() !== '') {
            return true;
        }
        foreach ($this->children() as $child) {
            if ($child->marked()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Answers the value as an SQL expression holds a JSON value: its JSON text, followed by a NUL byte and its typed text when the text does not tell every type.
     *
     * @example A decimal
     *     (new \MySqlMemory\Value\Json\JsonNode(\MySqlMemory\Value\Json\JsonKind::Decimal, '1.50'))->store() // => "1.50\0`d1.50"
     */
    public function store(): string
    {
        $text = $this->text();

        return $this->marked() ? $text . "\0" . $this->typed() : $text;
    }

    /**
     * Reads a JSON value as an SQL expression holds it ({@see self::store()}); a text that is no JSON document is read as a string.
     *
     * @example A decimal keeps its type
     *     \MySqlMemory\Value\Json\JsonNode::load("1.50\0`d1.50")->type // => \MySqlMemory\Value\Json\JsonKind::Decimal
     */
    public static function load(string $stored): self
    {
        $split = strpos($stored, "\0");
        if ($split !== false && ($stored[$split + 1] ?? '') !== '') {
            $at = $split + 1;

            return JsonReader::read($stored, $at);
        }
        try {
            return self::parse($stored, PHP_INT_MAX);
        } catch (JsonSyntax) {
            return new self(JsonKind::String, $stored);
        }
    }

    /**
     * Answers the type JSON_TYPE() names: the type in capitals, BIT or BLOB for an opaque value.
     *
     * @example An unsigned integer
     *     (new \MySqlMemory\Value\Json\JsonNode(\MySqlMemory\Value\Json\JsonKind::Unsigned, '7'))->name() // => 'UNSIGNED INTEGER'
     */
    public function name(): string
    {
        return match ($this->type) {
            JsonKind::Null => 'NULL',
            JsonKind::Boolean => 'BOOLEAN',
            JsonKind::Integer => 'INTEGER',
            JsonKind::Unsigned => 'UNSIGNED INTEGER',
            JsonKind::Double => 'DOUBLE',
            JsonKind::Decimal => 'DECIMAL',
            JsonKind::String => 'STRING',
            JsonKind::Array => 'ARRAY',
            JsonKind::Object => 'OBJECT',
            JsonKind::Date => 'DATE',
            JsonKind::Time => 'TIME',
            JsonKind::DateTime => 'DATETIME',
            JsonKind::Timestamp => 'TIMESTAMP',
            JsonKind::Opaque => $this->bit() ? 'BIT' : 'BLOB',
        };
    }

    /**
     * Tells whether the value is an opaque value made from a BIT value.
     *
     * @example A BIT value
     *     (new \MySqlMemory\Value\Json\JsonNode(\MySqlMemory\Value\Json\JsonKind::Opaque, 'base64:type16:BQ=='))->bit() // => true
     */
    public function bit(): bool
    {
        return $this->type === JsonKind::Opaque && str_starts_with($this->scalar(), 'base64:type16:');
    }

    /**
     * Answers the depth of the value: 1 for a scalar or an empty array or object, else one more than the deepest value it holds.
     *
     * @example A nested array
     *     \MySqlMemory\Value\Json\JsonNode::parse('[1, [2]]')->depth() // => 3
     */
    public function depth(): int
    {
        $deepest = 0;
        foreach ($this->children() as $child) {
            $deepest = max($deepest, $child->depth());
        }

        return $deepest + 1;
    }

    /**
     * Compares the value with another as the server orders JSON values: negative, zero or positive.
     *
     * @example A boolean is above any number
     *     \MySqlMemory\Value\Json\JsonNode::parse('true')->compare(\MySqlMemory\Value\Json\JsonNode::parse('5')) // => 1
     */
    public function compare(self $other): int
    {
        $rank = $this->rank() <=> $other->rank();
        if ($rank !== 0) {
            return $rank;
        }

        return match ($this->type) {
            JsonKind::Integer, JsonKind::Unsigned, JsonKind::Double, JsonKind::Decimal => Decimal::compare(self::number($this), self::number($other)),
            JsonKind::String, JsonKind::Date, JsonKind::DateTime, JsonKind::Timestamp, JsonKind::Opaque => strcmp($this->scalar(), $other->scalar()) <=> 0,
            JsonKind::Time => Order::time($this->scalar()) <=> Order::time($other->scalar()),
            JsonKind::Boolean => $this->value <=> $other->value,
            JsonKind::Null => 0,
            JsonKind::Array => $this->elements($other),
            JsonKind::Object => $this->members($other),
        };
    }

    /**
     * Answers a key equal for two values exactly when they compare equal.
     *
     * @example An integer and a double
     *     \MySqlMemory\Value\Json\JsonNode::parse('[1]')->key() === \MySqlMemory\Value\Json\JsonNode::parse('[1.0]')->key() // => true
     */
    public function key(): string
    {
        $rank = $this->rank() . ':';

        return match ($this->type) {
            JsonKind::Integer, JsonKind::Unsigned, JsonKind::Double, JsonKind::Decimal => $rank . self::number($this),
            JsonKind::Null => $rank,
            JsonKind::Boolean => $rank . ($this->value === true ? '1' : '0'),
            JsonKind::String, JsonKind::Date, JsonKind::DateTime, JsonKind::Timestamp, JsonKind::Opaque => $rank . strlen($this->scalar()) . ':' . $this->scalar(),
            JsonKind::Time => $rank . Order::time($this->scalar()),
            JsonKind::Array => $rank . '[' . implode(',', array_map(static fn (self $element): string => $element->key(), $this->children())) . ']',
            JsonKind::Object => $rank . '{' . implode(',', array_map(static fn (int|string $name, self $member): string => strlen((string) $name) . ':' . $name . '=' . $member->key(), array_keys(is_array($this->value) ? $this->value : []), $this->children())) . '}',
        };
    }

    /**
     * Answers the rank of the value among the types: that of its type, an opaque value made from a BIT value ranking below the others.
     *
     * @example A BLOB ranks above a BIT value
     *     (new \MySqlMemory\Value\Json\JsonNode(\MySqlMemory\Value\Json\JsonKind::Opaque, 'base64:type15:AQ=='))->rank() // => 10
     */
    public function rank(): int
    {
        return $this->type === JsonKind::Opaque && !$this->bit() ? 10 : $this->type->rank();
    }

    /**
     * Compares two arrays element by element, and then by their lengths.
     */
    public function elements(self $other): int
    {
        $mine = $this->children();
        $theirs = $other->children();
        foreach ($mine as $index => $element) {
            if (!isset($theirs[$index])) {
                return 1;
            }
            $compared = $element->compare($theirs[$index]);
            if ($compared !== 0) {
                return $compared;
            }
        }

        return count($mine) <=> count($theirs);
    }

    /**
     * Compares two objects by their numbers of members, and then member by member in written order, each by its name and then its value.
     */
    public function members(self $other): int
    {
        $names = [array_map('strval', array_keys(is_array($this->value) ? $this->value : [])), array_map('strval', array_keys(is_array($other->value) ? $other->value : []))];
        $values = [$this->children(), $other->children()];
        if (count($values[0]) !== count($values[1])) {
            return count($values[0]) <=> count($values[1]);
        }
        foreach ($values[0] as $index => $member) {
            $compared = strcmp($names[0][$index], $names[1][$index]) <=> 0;
            if ($compared === 0) {
                $compared = $member->compare($values[1][$index]);
            }
            if ($compared !== 0) {
                return $compared;
            }
        }

        return 0;
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
     * Writes the value as JSON_UNQUOTE() does: the characters of a string, a temporal or an opaque value, else the text of the value.
     *
     * @example A string
     *     (new \MySqlMemory\Value\Json\JsonNode(\MySqlMemory\Value\Json\JsonKind::String, 'a"b'))->unquoted() // => 'a"b'
     */
    public function unquoted(): string
    {
        return $this->type === JsonKind::String || $this->type->quoted() ? $this->scalar() : $this->text();
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
        return $this->compare($other) === 0;
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
