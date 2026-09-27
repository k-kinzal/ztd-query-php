<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Literal;

/**
 * A binary string, holding exactly its decoded bytes.
 * @example Reading the typed value
 *     bin2hex((new \SqlSemantics\Statement\Literal\BinaryLiteral("\0A"))->value()) // => '0041'
 * @visibility public
 */
final class BinaryLiteral implements Literal
{
    /**
     * Keeps an independent decoded scalar.
     */
    public function __construct(public readonly string $value)
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
