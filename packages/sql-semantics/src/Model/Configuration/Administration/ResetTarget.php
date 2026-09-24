<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Administration;

/**
 * One server state that a RESET option list discards.
 * @visibility public
 * @example Checking a reset target
 *     (new \SqlSemantics\Model\Configuration\Administration\QueryCacheReset())->availableIn(80044) // => false
 */
interface ResetTarget
{
    /**
     * Whether the target exists in the MySQL release numbered as major, minor and patch in two digits each.
     */
    public function availableIn(int $release): bool;
}
