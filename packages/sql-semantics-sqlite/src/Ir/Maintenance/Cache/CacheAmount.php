<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance\Cache;

use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A signed SQLite cache quantity in pages or, when negative, in kibibytes.
 *
 * @visibility public
 * @example Specifying a cache quantity of 128 KiB
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Maintenance\Cache\CacheAmount(-128))->value // => -128
 */
final class CacheAmount
{
    use Snapshot;

    /**
     * Keeps the exact signed native operand, including zero.
     * @throws InvalidConstruction When the operand is outside the native interval
     */
    public function __construct(public readonly int $value)
    {
        Check::input($value >= -2147483648 && $value <= 2147483647, 'The cache amount must be a signed 32-bit integer.');
    }
}
