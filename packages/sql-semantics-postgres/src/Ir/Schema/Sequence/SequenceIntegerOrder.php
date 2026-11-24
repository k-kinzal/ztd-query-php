<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence;

use SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerLiteral;

/**
 * Orders normalized integers at both signed 64-bit endpoints.
 *
 * @visibility SqlSemantics
 */
final class SequenceIntegerOrder
{
    /**
     * Compares the checked values in signed numeric order.
     */
    public static function compare(WideIntegerLiteral $left, WideIntegerLiteral $right): int
    {
        $negative = str_starts_with($left->decimal, '-');
        if ($negative !== str_starts_with($right->decimal, '-')) {
            return $negative ? -1 : 1;
        }
        $width = strlen($left->decimal) <=> strlen($right->decimal);
        $magnitude = $width === 0 ? strcmp($left->decimal, $right->decimal) : $width;

        return $negative ? -$magnitude : $magnitude;
    }
}
