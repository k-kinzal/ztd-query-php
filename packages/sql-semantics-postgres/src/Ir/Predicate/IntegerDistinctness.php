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
 * Null-safe equality or difference across the native signed widths.
 *
 * @visibility public
 * @example Comparing an int4 slot to NULL
 *     $test = new \SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerDistinctness(new \SqlSemantics\Platform\PostgreSql\Ir\ColumnReference(0), new \SqlSemantics\Platform\PostgreSql\Ir\NullLiteral());
 *     $test->negated // => false
 */
final class IntegerDistinctness
{
    use Snapshot;

    /**
     * Fixes both operands and whether NOT DISTINCT was requested.
     */
    public function __construct(
        public readonly ColumnReference|IntegerLiteral|NullLiteral|WideIntegerColumnReference|WideIntegerLiteral|WideIntegerNull $left,
        public readonly ColumnReference|IntegerLiteral|NullLiteral|WideIntegerColumnReference|WideIntegerLiteral|WideIntegerNull $right,
        public readonly bool $negated = false,
    ) {
    }
}
