<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Literal;

use InvalidArgumentException;

/**
 * An exact bit string; leading zeros and non-byte-aligned widths are significant.
 * @example Reading the typed value
 *     (new \SqlSemantics\Statement\Literal\BitLiteral('001'))->value() // => '001'
 * @example Rejecting invalid literal state
 *     new \SqlSemantics\Statement\Literal\BitLiteral('102') // throws \InvalidArgumentException
 * @visibility public
 */
final class BitLiteral implements Literal
{
    /**
     * Keeps an independent decoded scalar.
     * @throws InvalidArgumentException When a digit is not binary
     */
    public function __construct(public readonly string $value)
    {
        if (strspn($value, '01') !== strlen($value)) {
            throw new InvalidArgumentException('A bit string contains only zero and one.');
        }
    }

    /**
     * Returns the decoded PHP value without coercion.
     */
    public function value(): string
    {
        return $this->value;
    }
}
