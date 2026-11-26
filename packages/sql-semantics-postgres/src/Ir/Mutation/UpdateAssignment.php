<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Mutation;

use SqlSemantics\Platform\PostgreSql\Ir\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\TextLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\From\BooleanColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\From\TextColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\From\WideIntegerColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A scalar assignment relative to the target row.
 *
 * @visibility public
 * @example Reading the written slot number
 *     $assignment = new \SqlSemantics\Platform\PostgreSql\Ir\Mutation\UpdateAssignment(new \SqlSemantics\Platform\PostgreSql\Ir\ColumnReference(1), new \SqlSemantics\Platform\PostgreSql\Ir\NullLiteral());
 *     $assignment->target->slot // => 1
 */
final class UpdateAssignment
{
    use Snapshot;

    /**
     * Closes the write role and any read reference over one input occurrence.
     */
    public function __construct(
        public readonly ColumnReference|TextColumnReference|BooleanColumnReference|WideIntegerColumnReference $target,
        public readonly ColumnReference|IntegerLiteral|NullLiteral|TextColumnReference|TextLiteral|BooleanColumnReference|BooleanLiteral|WideIntegerColumnReference|WideIntegerLiteral $value,
    ) {
        Check::input($target->occurrence === 0, 'An UPDATE target slot must belong to occurrence zero.');
        $read = $value instanceof ColumnReference || $value instanceof TextColumnReference
            || $value instanceof BooleanColumnReference || $value instanceof WideIntegerColumnReference;
        Check::input(!$read || $value->occurrence === 0, 'An UPDATE read slot must belong to occurrence zero.');
        $signed = $value instanceof ColumnReference || $value instanceof IntegerLiteral
            || $value instanceof WideIntegerColumnReference || $value instanceof WideIntegerLiteral;
        $compatible = match (true) {
            $target instanceof ColumnReference, $target instanceof WideIntegerColumnReference => $signed,
            $target instanceof TextColumnReference => $value instanceof TextColumnReference || $value instanceof TextLiteral || $signed,
            $target instanceof BooleanColumnReference => $value instanceof BooleanColumnReference || $value instanceof BooleanLiteral,
        };
        Check::input($value instanceof NullLiteral || $compatible, 'An UPDATE assignment value must match its target native kind.');
    }
}
