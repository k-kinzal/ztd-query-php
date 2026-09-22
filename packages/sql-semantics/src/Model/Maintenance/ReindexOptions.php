<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Maintenance;

/**
 * PostgreSQL index rebuild policy, including the destination tablespace.
 * @visibility public
 * @example Requesting a concurrent rebuild
 *     $options = new \SqlSemantics\Model\Maintenance\ReindexOptions(concurrently: true);
 *     $options->concurrently // => true
 */
final class ReindexOptions
{
    /**
     * Records execution policy without executing the rebuild.
     */
    public function __construct(public readonly bool $concurrently = false, public readonly bool $verbose = false, public readonly ?string $tablespace = null)
    {
    }
}
