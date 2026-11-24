<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence;

use SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\IntegerStorage;

/**
 * Bounds and orders canonical native signed integers.
 *
 * @visibility SqlSemantics
 */
final class SequenceIntegerOrder
{
    /**
     * Returns the inclusive limits of the fixed storage width.
     *
     * @return array{WideIntegerLiteral, WideIntegerLiteral}
     */
    public static function limits(IntegerStorage $storage): array
    {
        [$floor, $ceiling] = match ($storage) {
            IntegerStorage::Small => ['-32768', '32767'],
            IntegerStorage::Standard => ['-2147483648', '2147483647'],
            IntegerStorage::Wide => ['-9223372036854775808', '9223372036854775807'],
        };

        return [new WideIntegerLiteral($floor), new WideIntegerLiteral($ceiling)];
    }

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
