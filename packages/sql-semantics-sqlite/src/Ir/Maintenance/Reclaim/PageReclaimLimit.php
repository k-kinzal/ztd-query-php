<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance\Reclaim;

use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * The native positive bound on incremental reclamation steps.
 *
 * @visibility public
 * @example Reclaiming at most two pages
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Maintenance\Reclaim\PageReclaimLimit(2))->pages // => 2
 */
final class PageReclaimLimit
{
    use Snapshot;

    /**
     * The signed native maximum, also used for an omitted bound.
     */
    public const MAXIMUM = 2147483647;

    /**
     * Keeps the exact positive bound.
     * @throws InvalidConstruction When the bound is outside the native interval
     */
    public function __construct(public readonly int $pages = self::MAXIMUM)
    {
        Check::input($pages >= 1 && $pages <= self::MAXIMUM, 'An incremental vacuum limit must be a positive signed 32-bit integer.');
    }
}
