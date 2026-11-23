<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Predicate;

use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerNull;
use SqlSemantics\Platform\PostgreSql\Ir\From\WideIntegerColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * An inclusive range test on the resolved native signed widths.
 *
 * @visibility public
 * @example Allowing the bounds in either order
 *     $test = new \SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerRange(new \SqlSemantics\Platform\PostgreSql\Ir\ColumnReference(0), new \SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral('3'), new \SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral('1'), symmetric: true);
 *     $test->symmetric // => true
 */
final class IntegerRange
{
    use Snapshot;

    /**
     * Stores pure operands so that repeating a comparison preserves the same row values.
     */
    public function __construct(
        public readonly ColumnReference|IntegerLiteral|NullLiteral|WideIntegerColumnReference|WideIntegerLiteral|WideIntegerNull $subject,
        public readonly ColumnReference|IntegerLiteral|NullLiteral|WideIntegerColumnReference|WideIntegerLiteral|WideIntegerNull $lower,
        public readonly ColumnReference|IntegerLiteral|NullLiteral|WideIntegerColumnReference|WideIntegerLiteral|WideIntegerNull $upper,
        public readonly bool $negated = false,
        public readonly bool $symmetric = false,
    ) {
    }
}
