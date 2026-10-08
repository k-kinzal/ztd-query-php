<?php

declare(strict_types=1);

namespace MySqlMemory\Value\Json;

use MySqlMemory\Value\Real;

/**
 * Reads JSON text as the server does and writes the document in the text the server returns.
 *
 * The text is read from its first byte; a failure is reported with the message the server gives
 * and the byte position where it was found. Whitespace is space, tab, line feed and carriage
 * return. A document nests at most 100 arrays and objects.
 *
 * The document is written with `", "` between values and `": "` after a name. The members of an
 * object are ordered by the length of their names, then by their bytes; of members with one name
 * the last is kept. A number is read as {@see JsonNumber} says. A string is written with `\"`,
 * `\\`, `\b`, `\f`, `\n`, `\r`, `\t` and `\u00XX` for the other control characters; every other
 * character is written as itself.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/json.html.
 *
 * @visibility MySqlMemory
 */
final class Json
{
    /**
     * The deepest nesting of arrays and objects a document may have.
     */
    public const MAX_DEPTH = 100;

    /**
     * The byte position the reading has reached.
     */
    public int $at = 0;

    /**
     * The arrays and objects open at the position.
     */
    public int $depth = 0;

    /**
     * @param string $text The JSON text
     * @param int $limit The deepest nesting the reading accepts
     */
    public function __construct(public readonly string $text, public readonly int $limit = self::MAX_DEPTH)
    {
    }

    /**
     * Reads a JSON text and answers the document in the text the server returns; a text nesting deeper than a limit, 100 by default, is refused.
     *
     * @example A document
     *     \MySqlMemory\Value\Json\Json::canonical('{"b":1,"a":[1.0,"x/y"]}') // => '{"a": [1.0, "x/y"], "b": 1}'
     *
     * @throws JsonSyntax When the text is not a JSON document
     */
    public static function canonical(string $text, int $limit = self::MAX_DEPTH): string
    {
        $json = new self($text, $limit);
        $json->space();
        if ($json->at >= strlen($text)) {
            throw new JsonSyntax('The document is empty.', $json->at);
        }
        $document = $json->value();
        $json->space();
        if ($json->at < strlen($text)) {
            throw new JsonSyntax('The document root must not be followed by other values.', $json->at);
        }

        return $document;
    }

    /**
     * Answers the JSON text of a JSON value as an SQL expression holds it, without the typed text that may follow it ({@see JsonNode::store()}).
     *
     * @example A decimal
     *     \MySqlMemory\Value\Json\Json::visible("1.50\0`d1.50") // => '1.50'
     */
    public static function visible(string $stored): string
    {
        $split = strpos($stored, "\0");

        return $split === false ? $stored : substr($stored, 0, $split);
    }

    /**
     * Skips whitespace.
     */
    public function space(): void
    {
        $this->at += strspn($this->text, " \t\n\r", $this->at);
    }

    /**
     * Reads one value at the position.
     *
     * @throws JsonSyntax When no value starts at the position
     */
    public function value(): string
    {
        $next = $this->text[$this->at] ?? '';

        return match (true) {
            $next === '{' => $this->object(),
            $next === '[' => $this->array(),
            $next === '"' => self::quote($this->string()),
            $next === 't' => $this->literal('true'),
            $next === 'f' => $this->literal('false'),
            $next === 'n' => $this->literal('null'),
            $next === '-' || ctype_digit($next) => (new JsonNumber($this))->read(),
            default => throw new JsonSyntax('Invalid value.', $this->at),
        };
    }

    /**
     * Enters an array or an object whose opening character is at the position.
     *
     * @throws JsonSyntax When the document nests too deeply
     */
    public function enter(): void
    {
        $this->at++;
        $this->depth++;
        if ($this->depth > $this->limit) {
            throw new JsonSyntax('Terminate parsing due to Handler error.', $this->at, true);
        }
        $this->space();
    }

    /**
     * Reads an object.
     *
     * @throws JsonSyntax When the object is malformed
     */
    public function object(): string
    {
        $this->enter();
        $members = [];
        if (($this->text[$this->at] ?? '') === '}') {
            $this->at++;
            $this->depth--;

            return '{}';
        }
        while (true) {
            if (($this->text[$this->at] ?? '') !== '"') {
                throw new JsonSyntax('Missing a name for object member.', $this->at);
            }
            $name = $this->string();
            $this->space();
            if (($this->text[$this->at] ?? '') !== ':') {
                throw new JsonSyntax('Missing a colon after a name of object member.', $this->at);
            }
            $this->at++;
            $this->space();
            unset($members[$name]);
            $members[$name] = $this->value();
            $this->space();
            $next = $this->text[$this->at] ?? '';
            $this->at++;
            if ($next === '}') {
                break;
            }
            if ($next !== ',') {
                throw new JsonSyntax("Missing a comma or '}' after an object member.", $this->at - 1);
            }
            $this->space();
        }
        $this->depth--;
        $names = array_map('strval', array_keys($members));
        usort($names, static fn (string $left, string $right): int => strlen($left) === strlen($right) ? strcmp($left, $right) : strlen($left) <=> strlen($right));

        return '{' . implode(', ', array_map(static fn (string $name): string => self::quote($name) . ': ' . $members[$name], $names)) . '}';
    }

    /**
     * Reads an array.
     *
     * @throws JsonSyntax When the array is malformed
     */
    public function array(): string
    {
        $this->enter();
        $elements = [];
        if (($this->text[$this->at] ?? '') === ']') {
            $this->at++;
            $this->depth--;

            return '[]';
        }
        while (true) {
            $elements[] = $this->value();
            $this->space();
            $next = $this->text[$this->at] ?? '';
            $this->at++;
            if ($next === ']') {
                break;
            }
            if ($next !== ',') {
                throw new JsonSyntax("Missing a comma or ']' after an array element.", $this->at - 1);
            }
            $this->space();
        }
        $this->depth--;

        return '[' . implode(', ', $elements) . ']';
    }

    /**
     * Reads true, false or null; a wrong character is reported where it is.
     *
     * @throws JsonSyntax When the word is misspelt
     */
    public function literal(string $word): string
    {
        for ($index = 1; $index < strlen($word); $index++) {
            if (($this->text[$this->at + $index] ?? '') !== $word[$index]) {
                throw new JsonSyntax('Invalid value.', $this->at + $index);
            }
        }
        $this->at += strlen($word);

        return $word;
    }

    /**
     * Reads a string and answers its characters.
     *
     * @throws JsonSyntax When the string is unterminated or holds a bad escape or a control character
     */
    public function string(): string
    {
        $this->at++;
        $characters = '';
        $length = strlen($this->text);
        while (true) {
            $plain = strcspn($this->text, "\"\\\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0a\x0b\x0c\x0d\x0e\x0f\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1a\x1b\x1c\x1d\x1e\x1f", $this->at);
            $characters .= substr($this->text, $this->at, $plain);
            $this->at += $plain;
            if ($this->at >= $length) {
                throw new JsonSyntax('Missing a closing quotation mark in string.', $this->at);
            }
            $next = $this->text[$this->at];
            if ($next === '"') {
                $this->at++;

                return $characters;
            }
            if ($next !== '\\') {
                throw new JsonSyntax('Invalid encoding in string.', $this->at);
            }
            $characters .= $this->escape();
        }
    }

    /**
     * Reads an escape sequence at the position and answers the character it writes.
     *
     * @throws JsonSyntax When the escape is not valid
     */
    public function escape(): string
    {
        $start = $this->at;
        $letter = $this->text[$this->at + 1] ?? '';
        $simple = ['"' => '"', '\\' => '\\', '/' => '/', 'b' => "\x08", 'f' => "\x0c", 'n' => "\n", 'r' => "\r", 't' => "\t"];
        if (isset($simple[$letter])) {
            $this->at += 2;

            return $simple[$letter];
        }
        if ($letter !== 'u') {
            throw new JsonSyntax('Invalid escape character in string.', $start);
        }
        $code = $this->hex($start);
        if ($code >= 0xDC00 && $code <= 0xDFFF) {
            throw new JsonSyntax('The surrogate pair in string is invalid.', $start);
        }
        if ($code >= 0xD800 && $code <= 0xDBFF) {
            if (substr($this->text, $this->at, 2) !== '\\u') {
                throw new JsonSyntax('The surrogate pair in string is invalid.', $start);
            }
            $low = $this->hex($start);
            if ($low < 0xDC00 || $low > 0xDFFF) {
                throw new JsonSyntax('The surrogate pair in string is invalid.', $start);
            }
            $code = 0x10000 + (($code - 0xD800) << 10) + ($low - 0xDC00);
        }

        return mb_chr($code, 'UTF-8');
    }

    /**
     * Reads the four hexadecimal digits of a `\u` escape at the position.
     *
     * @param int $start The position of the escape the digits belong to, where a failure is reported
     *
     * @throws JsonSyntax When a digit is not hexadecimal
     */
    public function hex(int $start): int
    {
        $digits = substr($this->text, $this->at + 2, 4);
        if (strlen($digits) !== 4 || !ctype_xdigit($digits)) {
            throw new JsonSyntax('Incorrect hex digit after \u escape in string.', $start);
        }
        $this->at += 6;

        return (int) hexdec($digits);
    }

    /**
     * Writes a double as the server writes it in a JSON document.
     *
     * The shortest digits that read back as the same number are written in positional notation
     * with at least one decimal (`100.0`), unless the point lies more than 15 digits after the
     * first digit with no digit after it, or more than 14 zeros follow the point before the first
     * digit; then the digits are written with an exponent (`1e15`, `1.5e-16`).
     */
    public static function double(float $value): string
    {
        if ($value === 0.0) {
            return fdiv(1, $value) < 0 ? '-0.0' : '0.0';
        }
        [$digits, $point] = Real::digits(abs($value));
        $sign = $value < 0 ? '-' : '';
        $length = strlen($digits);
        if (($point > 15 && $point >= $length) || $point <= -15) {
            return $sign . ($length > 1 ? $digits[0] . '.' . substr($digits, 1) : $digits) . 'e' . ($point - 1);
        }
        if ($point <= 0) {
            return $sign . '0.' . str_repeat('0', -$point) . $digits;
        }
        if ($point < $length) {
            return $sign . substr($digits, 0, $point) . '.' . substr($digits, $point);
        }

        return $sign . $digits . str_repeat('0', $point - $length) . '.0';
    }

    /**
     * Writes a string in quotation marks with the escapes the server writes.
     */
    public static function quote(string $characters): string
    {
        $escaped = preg_replace_callback('/[\x00-\x1f"\\\\]/', static fn (array $match): string => match ($match[0]) {
            '"' => '\\"',
            '\\' => '\\\\',
            "\x08" => '\\b',
            "\x0c" => '\\f',
            "\n" => '\\n',
            "\r" => '\\r',
            "\t" => '\\t',
            default => sprintf('\\u%04x', ord($match[0])),
        }, $characters);

        return '"' . $escaped . '"';
    }
}
