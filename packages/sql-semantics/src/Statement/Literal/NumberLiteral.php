<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Literal;

use RangeException;

/**
 * An exact number spelling in base ten, including its decimal point or exponent.
 *
 * This is the literal's value before a server applies a storage type or rounds
 * it to floating point. Integer conversion is explicit and checked.
 * @example Reading the typed value
 *     (new \SqlSemantics\Statement\Literal\NumberLiteral('42'))->toInt() // => 42
 * @visibility public
 */
final class NumberLiteral implements Literal
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * Asserts the construction invariants of the semantic value.
     */
    public function __construct(public readonly string $value)
    {
        \SqlSemantics\Statement\Validation\Check::input(preg_match('/\A-?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?\z/D', $value) === 1, 'A decoded number must be exact decimal text.');
    }

    /**
     * Returns the decoded PHP value without coercion.
     */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * @throws RangeException When the spelling is not an integer that fits in PHP
     */
    public function toInt(): int
    {
        $negative = str_starts_with($this->value, '-');
        $digits = ltrim(ltrim($this->value, '-'), '0');
        $normalized = $digits === '' ? '0' : ($negative ? '-' : '') . $digits;
        $value = filter_var($normalized, FILTER_VALIDATE_INT);
        if ($value === false) {
            throw new RangeException('The literal is not an integer representable by PHP.');
        }
        return $value;
    }
}
