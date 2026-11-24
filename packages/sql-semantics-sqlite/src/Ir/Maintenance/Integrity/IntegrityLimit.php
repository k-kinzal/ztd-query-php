<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance\Integrity;

use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A positive SQLite integrity report limit.
 *
 * @visibility public
 * @example Reporting at most three anomalies
 *     $limit = new \SqlSemantics\Platform\Sqlite\Ir\Maintenance\Integrity\IntegrityLimit(3);
 *     $limit->value // => 3
 */
final class IntegrityLimit
{
    use Snapshot;

    /**
     * The report limit used by the pinned engine when no limit is specified.
     */
    public const DEFAULT_VALUE = 100;
    /**
     * The largest report limit accepted by the native integer parser.
     */
    public const MAX_VALUE = 2147483647;

    /**
     * Fixes the report budget used by SQLite.
     * @throws InvalidConstruction When the limit falls outside the native positive range
     */
    public function __construct(public readonly int $value = self::DEFAULT_VALUE)
    {
        Check::input($value >= 1 && $value <= self::MAX_VALUE, 'An integrity report limit must be a positive signed 32-bit integer.');
    }
}
