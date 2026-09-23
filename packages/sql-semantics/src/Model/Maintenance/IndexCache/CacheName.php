<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Maintenance\IndexCache;

/**
 * A key cache identified by its server configuration name; no cache is allocated.
 * @visibility public
 * @example Naming an existing key cache
 *     (new \SqlSemantics\Model\Maintenance\IndexCache\CacheName('hot'))->name // => 'hot'
 */
final class CacheName
{
    /**
     * Retains the configuration identifier without testing whether its cache exists.
     */
    public function __construct(public readonly string $name)
    {
    }
}
