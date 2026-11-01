<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Text\Json;

/**
 * Checks the intrinsic JSON document grammar of engine attributes.
 *
 * A document contains one value and nests at most 100 arrays or objects. Errors
 * identify a decoded operand byte, independently of SQL text or database state.
 * The empty attribute is handled by Attribute, outside the JSON grammar.
 * Observed through the MySQL 8.4.7 SQL interface. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/create-table.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Syntax
{
    /**
     * The next decoded operand byte.
     */
    public int $at = 0;
    /**
     * The number of open JSON containers.
     */
    public int $depth = 0;

    /**
     * @param string $text The decoded JSON operand
     */
    public function __construct(public readonly string $text)
    {
    }

    /**
     * Checks one complete document without constructing an execution value.
     *
     * @throws Failure When a document is malformed or too deeply nested
     */
    public function check(): void
    {
        $this->space();
        if ($this->at === strlen($this->text)) {
            throw new Failure('The document is empty.', $this->at);
        }
        $this->value();
        $this->space();
        if ($this->at !== strlen($this->text)) {
            throw new Failure('The document root must not be followed by other values.', $this->at);
        }
    }

    /**
     * Skips JSON whitespace.
     */
    public function space(): void
    {
        $this->at += strspn($this->text, " \t\n\r", $this->at);
    }

    /**
     * Reads one JSON value.
     *
     * @throws Failure When the next bytes cannot denote a value
     */
    public function value(): void
    {
        $next = $this->text[$this->at] ?? '';
        match (true) {
            $next === '{' => $this->object(),
            $next === '[' => $this->array(),
            $next === '"' => $this->string(),
            $next === 't' => $this->literal('true'),
            $next === 'f' => $this->literal('false'),
            $next === 'n' => $this->literal('null'),
            $next === '-' || ctype_digit($next) => $this->number(),
            default => throw new Failure('Invalid value.', $this->at),
        };
    }

    /**
     * Begins a container and enforces the intrinsic depth bound.
     *
     * @throws Failure When a container exceeds 100 levels
     */
    public function enter(): void
    {
        $this->at++;
        $this->depth++;
        if ($this->depth > 100) {
            throw new Failure('Terminate parsing due to Handler error.', $this->at, true);
        }
        $this->space();
    }

    /**
     * Reads an object with string member names.
     *
     * @throws Failure When a member or separator is malformed
     */
    public function object(): void
    {
        $this->enter();
        if (($this->text[$this->at] ?? '') === '}') {
            $this->at++;
            $this->depth--;
            return;
        }
        while (true) {
            if (($this->text[$this->at] ?? '') !== '"') {
                throw new Failure('Missing a name for object member.', $this->at);
            }
            $this->string();
            $this->space();
            if (($this->text[$this->at] ?? '') !== ':') {
                throw new Failure('Missing a colon after a name of object member.', $this->at);
            }
            $this->at++;
            $this->space();
            $this->value();
            $this->space();
            $next = $this->text[$this->at] ?? '';
            $this->at++;
            if ($next === '}') {
                break;
            }
            if ($next !== ',') {
                throw new Failure("Missing a comma or '}' after an object member.", $this->at - 1);
            }
            $this->space();
        }
        $this->depth--;
    }

    /**
     * Reads an array of values.
     *
     * @throws Failure When an element or separator is malformed
     */
    public function array(): void
    {
        $this->enter();
        if (($this->text[$this->at] ?? '') === ']') {
            $this->at++;
            $this->depth--;
            return;
        }
        while (true) {
            $this->value();
            $this->space();
            $next = $this->text[$this->at] ?? '';
            $this->at++;
            if ($next === ']') {
                break;
            }
            if ($next !== ',') {
                throw new Failure("Missing a comma or ']' after an array element.", $this->at - 1);
            }
            $this->space();
        }
        $this->depth--;
    }

    /**
     * Reads true, false or null and reports a differing byte.
     *
     * @throws Failure When the word is incomplete or misspelled
     */
    public function literal(string $word): void
    {
        for ($index = 1; $index < strlen($word); $index++) {
            if (($this->text[$this->at + $index] ?? '') !== $word[$index]) {
                throw new Failure('Invalid value.', $this->at + $index);
            }
        }
        $this->at += strlen($word);
    }

    /**
     * Reads a JSON string, including escapes and Unicode surrogate pairs.
     *
     * @throws Failure When the quoted text is incomplete or malformed
     */
    public function string(): void
    {
        $this->at++;
        while (true) {
            $plain = strcspn($this->text, "\"\\\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0a\x0b\x0c\x0d\x0e\x0f\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1a\x1b\x1c\x1d\x1e\x1f", $this->at);
            $this->at += $plain;
            if ($this->at >= strlen($this->text)) {
                throw new Failure('Missing a closing quotation mark in string.', $this->at);
            }
            $next = $this->text[$this->at];
            if ($next === '"') {
                $this->at++;
                return;
            }
            if ($next !== '\\') {
                throw new Failure('Invalid encoding in string.', $this->at);
            }
            $this->escape();
        }
    }

    /**
     * Reads an escape at the current backslash.
     *
     * @throws Failure When an escape or surrogate pair is invalid
     */
    public function escape(): void
    {
        $start = $this->at;
        $letter = $this->text[$this->at + 1] ?? '';
        if (in_array($letter, ['"', '\\', '/', 'b', 'f', 'n', 'r', 't'], true)) {
            $this->at += 2;
            return;
        }
        if ($letter !== 'u') {
            throw new Failure('Invalid escape character in string.', $start);
        }
        $code = $this->hex($start);
        if ($code >= 0xDC00 && $code <= 0xDFFF) {
            throw new Failure('The surrogate pair in string is invalid.', $start);
        }
        if ($code >= 0xD800 && $code <= 0xDBFF) {
            if (substr($this->text, $this->at, 2) !== '\\u') {
                throw new Failure('The surrogate pair in string is invalid.', $start);
            }
            $low = $this->hex($start);
            if ($low < 0xDC00 || $low > 0xDFFF) {
                throw new Failure('The surrogate pair in string is invalid.', $start);
            }
        }
    }

    /**
     * Reads four hexadecimal digits of a Unicode escape.
     *
     * @throws Failure When a digit is missing or not hexadecimal
     */
    public function hex(int $start): int
    {
        $digits = substr($this->text, $this->at + 2, 4);
        if (strlen($digits) !== 4 || !ctype_xdigit($digits)) {
            throw new Failure('Incorrect hex digit after \u escape in string.', $start);
        }
        $this->at += 6;
        return (int) hexdec($digits);
    }

    /**
     * Reads decimal JSON syntax and its finite double range.
     *
     * @throws Failure When numeric syntax or magnitude is invalid
     */
    public function number(): void
    {
        $start = $this->at;
        if ($this->text[$this->at] === '-') {
            $this->at++;
        }
        $integer = strspn($this->text, '0123456789', $this->at);
        if ($integer === 0) {
            throw new Failure('Invalid value.', $this->at);
        }
        $this->at += $this->text[$this->at] === '0' ? 1 : $integer;
        $fraction = 0;
        if (($this->text[$this->at] ?? '') === '.') {
            $this->at++;
            $fraction = strspn($this->text, '0123456789', $this->at);
            if ($fraction === 0) {
                throw new Failure('Miss fraction part in number.', $this->at);
            }
            $this->at += $fraction;
        }
        $exponent = in_array($this->text[$this->at] ?? '', ['e', 'E'], true) ? $this->exponent() : null;
        if (($exponent !== null && $exponent > 308 + $fraction) || is_infinite((float) substr($this->text, $start, $this->at - $start))) {
            throw new Failure('Number too big to be stored in double.', $start);
        }
    }

    /**
     * Reads an exponent, retaining the sign of arbitrarily long magnitudes.
     *
     * @throws Failure When no exponent digit is present
     */
    public function exponent(): int
    {
        $this->at++;
        $sign = in_array($this->text[$this->at] ?? '', ['+', '-'], true) ? $this->text[$this->at++] : '+';
        $digits = strspn($this->text, '0123456789', $this->at);
        if ($digits === 0) {
            throw new Failure('Miss exponent in number.', $this->at);
        }
        $magnitude = ltrim(substr($this->text, $this->at, $digits), '0');
        $this->at += $digits;
        return (strlen($magnitude) > 9 ? 999999999 : (int) $magnitude) * ($sign === '-' ? -1 : 1);
    }
}
