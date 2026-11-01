<?php

declare(strict_types=1);

namespace SqlSemantics\Statement;

use SqlSemantics\Statement\Source\Span;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Statement\Type\TypeFact;

/**
 * An expression carrying its own type, nullability and optional input location.
 *
 * The expression type describes its value before a destination converts it.
 * Missing inputs are represented by a dependent type, never invented bindings.
 *
 * @visibility public
 * @example A generated string expression has nullability without any SQL source
 *     $expression = new \SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral(['42']);
 *     [$expression->nullability(), $expression->source()] // => [\SqlSemantics\Statement\Type\Nullability::NotNull, null]
 */
interface Expression extends Node
{
    /**
     * Answers the expression's type before assignment conversion.
     */
    public function dataType(): TypeFact;

    /**
     * Answers whether evaluating the expression can produce NULL.
     */
    public function nullability(): Nullability;

    /**
     * Answers the original input location, absent for directly constructed values.
     */
    public function source(): ?Span;
}
