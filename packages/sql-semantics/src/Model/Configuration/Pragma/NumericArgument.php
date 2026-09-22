<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Pragma;

/**
 * A classified pragma argument.
 *
 * @visibility public
  * @example Inspecting NumericArgument
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::Sqlite))->build());
 *     $statement = $binder->bind('PRAGMA main.cache_size=-2000');
 *     $statement->value instanceof \SqlSemantics\Model\Configuration\Pragma\NumericArgument // => true
 */
final class NumericArgument implements Argument
{
    /**
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(public readonly \SqlSemantics\Model\Scalar\Value\Literal $literal, public readonly Sign $sign = Sign::Unsigned)
    {
        if ($literal->literalKind !== \SqlSemantics\Model\Scalar\Value\LiteralKind::Number) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('A pragma numeric argument requires a numeric literal.');
        }
    }
}
