<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Administration;

use Override;

/**
 * RESET QUERY CACHE: removes every cached query result.
 * @visibility public
 * @example Checking the release
 *     (new \SqlSemantics\Model\Configuration\Administration\QueryCacheReset())->availableIn(50744) // => true
 */
final class QueryCacheReset implements ResetTarget
{
    /**
     * The query cache exists before MySQL 8.0.
     */
    #[Override]
    public function availableIn(int $release): bool
    {
        return $release < 80000;
    }
}
