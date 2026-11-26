<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Ordering;

use SqlSemantics\Platform\PostgreSql\Ir\Arithmetic;
use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\BuiltinCast;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\CollatedText;
use SqlSemantics\Platform\PostgreSql\Ir\From\BooleanColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\From\TextColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\From\WideIntegerColumnReference;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A native sort key on an output position or a resolved source expression.
 *
 * @visibility public
 * @example Reading the native NULL placement
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Ordering\Order(1))->nullsFirst // => false
 */
final class Order
{
    use Snapshot;

    /**
     * Whether this key places NULL ahead of every non-NULL value.
     */
    public readonly bool $nullsFirst;

    /**
     * Fixes the ordered value, its direction and its effective placement of NULL.
     *
     * @param int|Arithmetic|BooleanColumnReference|BuiltinCast|CollatedText|ColumnReference|TextColumnReference|WideIntegerColumnReference $target A one-based output position or an input expression
     */
    public function __construct(
        public readonly int|Arithmetic|BooleanColumnReference|BuiltinCast|CollatedText|ColumnReference|TextColumnReference|WideIntegerColumnReference $target,
        public readonly bool $descending = false,
        ?bool $nullsFirst = null,
    ) {
        Check::input(!is_int($target) || $target >= 1, 'An output order position must be positive.');
        $this->nullsFirst = $nullsFirst ?? $descending;
    }
}
