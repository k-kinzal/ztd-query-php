<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Literal;

/**
 * A decoded SQL literal, independent of any column conversion or expression evaluation.
 * @example Reading the typed value
 *     (new \SqlSemantics\Statement\Literal\StringLiteral('x'))->value() // => 'x'
 * @visibility public
 */
interface Literal
{
    /**
     * Returns a PHP scalar; numbers use exact decimal text, never an implicit float conversion.
     */
    public function value(): string|bool|null;
}
