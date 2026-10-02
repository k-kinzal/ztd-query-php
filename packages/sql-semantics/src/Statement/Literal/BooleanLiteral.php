<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Literal;

/**
 * An explicit SQL truth literal.
 * @example Reading the typed value
 *     (new \SqlSemantics\Statement\Literal\BooleanLiteral(false))->value() // => false
 * @visibility public
 */
final class BooleanLiteral implements Literal
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * Keeps an independent decoded scalar.
     */
    public function __construct(public readonly bool $value)
    {
    }

    /**
     * Returns the decoded PHP value without coercion.
     */
    public function value(): bool
    {
        return $this->value;
    }
}
