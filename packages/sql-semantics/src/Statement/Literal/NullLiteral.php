<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Literal;

/**
 * SQL NULL, distinct from failure to decode an expression.
 * @example Reading the typed value
 *     \SqlSemantics\Statement\Literal\NullLiteral::Null->value() // => null
 * @visibility public
 */
enum NullLiteral implements Literal
{
    case Null;

    /**
     * Returns the decoded PHP value without coercion.
     */
    public function value(): string|bool|null
    {
        return null;
    }
}
