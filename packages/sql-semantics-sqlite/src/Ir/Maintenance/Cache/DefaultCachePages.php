<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance\Cache;

use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * The native normalized persistent cache capacity in pages.
 *
 * @visibility public
 * @example Recording a 64-page persistent default
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Maintenance\Cache\DefaultCachePages(64))->pages // => 64
 */
final class DefaultCachePages
{
    use Snapshot;

    /**
     * Keeps the exact page count. Zero resets the persistent default cookie.
     * @throws InvalidConstruction When the count is outside the native interval
     */
    public function __construct(public readonly int $pages)
    {
        Check::input($pages >= 0 && $pages <= 2147483647, 'The persistent cache default needs a nonnegative signed 32-bit page count.');
    }
}
