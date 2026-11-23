<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema;

use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\Predicate\RangeIntegerLiteral;

/**
 * The native signed integer storage of a declared column.
 *
 * @visibility public
 * @example Reading the small signed integer spelling
 *     \SqlSemantics\Platform\MySql\Ir\Schema\IntegerStorage::Small->value // => 'SMALLINT'
 */
enum IntegerStorage: string
{
    case Tiny = 'TINYINT';
    case Small = 'SMALLINT';
    case Medium = 'MEDIUMINT';
    case Standard = 'INTEGER';
    case Wide = 'BIGINT';

    /**
     * Gives the fixed size of a field in the logical row layout.
     */
    public function bytes(): int
    {
        return match ($this) {
            self::Tiny => 1,
            self::Small => 2,
            self::Medium => 3,
            self::Standard => 4,
            self::Wide => 8,
        };
    }

    /**
     * Checks the exact signed storage range using canonical decimal magnitudes.
     */
    public function accepts(IntegerLiteral|RangeIntegerLiteral $literal): bool
    {
        $negative = $literal->decimal[0] === '-';
        $limit = match ($this) {
            self::Tiny => $negative ? '128' : '127',
            self::Small => $negative ? '32768' : '32767',
            self::Medium => $negative ? '8388608' : '8388607',
            self::Standard => $negative ? '2147483648' : '2147483647',
            self::Wide => $negative ? '9223372036854775808' : '9223372036854775807',
        };
        $magnitude = ltrim($literal->decimal, '-');
        $digits = strlen($magnitude);

        return $digits < strlen($limit) || ($digits === strlen($limit) && strcmp($magnitude, $limit) <= 0);
    }
}
