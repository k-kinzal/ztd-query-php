<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Literal;

/**
 * An exact bit string; leading zeros and non-byte-aligned widths are significant.
 * @example Reading the typed value
 *     (new \SqlSemantics\Statement\Literal\BitLiteral('001'))->value() // => '001'
 * @visibility public
 */
final class BitLiteral implements Literal
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * Keeps an independent decoded scalar.
     */
    public function __construct(public readonly string $value)
    {
        \SqlSemantics\Statement\Validation\Check::input(strspn($value, '01') === strlen($value), 'A bit string contains only zero and one.');
    }

    /**
     * Returns the decoded PHP value without coercion.
     */
    public function value(): string
    {
        return $this->value;
    }
}
