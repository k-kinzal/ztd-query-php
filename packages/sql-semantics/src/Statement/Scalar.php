<?php

declare(strict_types=1);

namespace SqlSemantics\Statement;

/**
 * A scalar expression: an operation over operands that yields one value per evaluation.
 *
 * Each occurrence owns its type, nullability, name resolution and optional
 * source range. Binding completes those values before consumers execute it.
 *
 * @visibility public
 * @example Reading the facts an operation derived for an expression
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT 1');
 *     $operation->field(0)->expression->scalarInformation()->nullability // => \SqlSemantics\Statement\Type\Nullability::NotNull
 */
interface Scalar extends Operand
{
}
