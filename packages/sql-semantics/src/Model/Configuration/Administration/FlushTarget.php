<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Administration;

/**
 * One cache, log or state that a FLUSH option list reloads or reopens.
 * @visibility public
 * @example Reading a flush target
 *     \SqlSemantics\Model\Configuration\Administration\ServerFlush::Privileges instanceof \SqlSemantics\Model\Configuration\Administration\FlushTarget // => true
 */
interface FlushTarget
{
    /**
     * Whether the target exists in the MySQL release numbered as major, minor and patch in two digits each.
     */
    public function availableIn(int $release): bool;
}
