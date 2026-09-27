<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Literal;

/**
 * Decoded string bytes, with an optional character set introducer.
 * @example Reading the typed value
 *     (new \SqlSemantics\Statement\Literal\StringLiteral('x'))->value() // => 'x'
 * @visibility public
 */
final class StringLiteral implements Literal
{
    /**
     * Keeps an independent decoded scalar.
     */
    public function __construct(public readonly string $value, public readonly ?string $characterSet = null)
    {
    }

    /**
     * Returns the decoded PHP value without coercion.
     */
    public function value(): string
    {
        return $this->value;
    }
}
