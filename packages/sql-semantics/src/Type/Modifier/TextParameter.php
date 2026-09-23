<?php

declare(strict_types=1);

namespace SqlSemantics\Type\Modifier;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A PostgreSQL type-modifier text operand, without invoking the type's input function.
 * @visibility public
 * @example Retaining a text modifier without converting it to a number
 *     $value = \SqlSemantics\Model\Expression::literal('12', \SqlSemantics\Dialect::PostgreSql);
 *     (new \SqlSemantics\Type\Modifier\TextParameter($value))->value->text // => "'12'"
 */
final class TextParameter
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly Literal $value)
    {
        if ($value->type->dialect !== Dialect::PostgreSql || $value->literalKind !== LiteralKind::Text) {
            throw new InvalidStructure('A text type modifier requires a PostgreSQL text literal.');
        }
    }
}
