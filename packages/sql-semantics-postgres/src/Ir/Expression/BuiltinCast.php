<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Expression;

use SqlSemantics\Platform\PostgreSql\Ir\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * An explicit conversion of an admitted scalar to a concrete pg_catalog type.
 *
 * @visibility public
 * @example Typing a NULL as boolean
 *     $target = \SqlSemantics\Platform\PostgreSql\Ir\Expression\CastDestination::Boolean;
 *     $cast = new \SqlSemantics\Platform\PostgreSql\Ir\Expression\BuiltinCast($target, new \SqlSemantics\Platform\PostgreSql\Ir\NullLiteral());
 *     $cast->target->value // => 'bool'
 */
final class BuiltinCast
{
    use Snapshot;

    /**
     * Owns the immutable input occurrence and the resolved cast target.
     */
    public function __construct(
        public readonly CastDestination $target,
        public readonly BooleanLiteral|ColumnReference|IntegerLiteral|NullLiteral|self $operand,
    ) {
    }
}
