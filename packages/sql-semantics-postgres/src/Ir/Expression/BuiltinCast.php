<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Expression;

use SqlSemantics\Platform\PostgreSql\Ir\Binding\Parameter;
use SqlSemantics\Platform\PostgreSql\Ir\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\From\BooleanColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\From\TextColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\From\WideIntegerColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
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
        public readonly BooleanLiteral|ColumnReference|IntegerLiteral|NullLiteral|Parameter|TextLiteral|WideIntegerLiteral|BooleanColumnReference|TextColumnReference|WideIntegerColumnReference|self $operand,
    ) {
        $source = match (true) {
            $operand instanceof BooleanLiteral, $operand instanceof BooleanColumnReference => CastDestination::Boolean,
            $operand instanceof WideIntegerLiteral, $operand instanceof WideIntegerColumnReference => CastDestination::WideInteger,
            $operand instanceof TextLiteral, $operand instanceof TextColumnReference => CastDestination::Text,
            $operand instanceof self => $operand->target,
            $operand instanceof Parameter => $operand->input,
            $operand instanceof ColumnReference, $operand instanceof IntegerLiteral => CastDestination::Integer,
            $operand instanceof NullLiteral => null,
        };
        Check::input(
            !($source === CastDestination::Boolean && $target === CastDestination::WideInteger)
                && !($source === CastDestination::WideInteger && $target === CastDestination::Boolean),
            'PostgreSQL has no direct cast between bigint and boolean.',
        );
        Check::input(
            $source !== CastDestination::Text || $target === CastDestination::Text,
            'A typed text operand requires a text conversion in this profile.',
        );
    }
}
