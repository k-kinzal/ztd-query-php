<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse\Definition;

use RuntimeException;

/**
 * Returns a typed pending definition from the input boundary to preparation.
 * This control transfer is neither a SQL error nor an ordinary semantic value.
 *
 * @visibility MySqlMemory
 */
final class DeferredPreparation extends RuntimeException
{
    /**
     * @param \MySqlMemory\Command\Prepared\PendingTable $definition The retained execution-time condition
     */
    public function __construct(public readonly \MySqlMemory\Command\Prepared\PendingTable $definition)
    {
        parent::__construct('The prepared table definition retains an execution-time partition condition.');
    }
}
