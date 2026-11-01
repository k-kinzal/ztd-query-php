<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse\Definition;

use RuntimeException;

/**
 * Returns an incomplete table definition from the input boundary to preparation.
 * This control transfer is neither a SQL error nor an ordinary semantic value.
 *
 * @visibility MySqlMemory
 */
final class DeferredPreparation extends RuntimeException
{
    /**
     * @param \MySqlMemory\Command\Prepared\Pending $definition The retained execution-time condition
     */
    public function __construct(public readonly \MySqlMemory\Command\Prepared\Pending $definition)
    {
        parent::__construct('The prepared instruction retains a condition resolved during execution.');
    }
}
