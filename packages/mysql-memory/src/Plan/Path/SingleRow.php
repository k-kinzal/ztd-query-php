<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Path;

/**
 * Produces one row without values: the input of a query block without a FROM clause.
 *
 * @visibility MySqlMemory
 */
final class SingleRow implements AccessPath
{
    /**
     * Answers zero: the row has no values.
     */
    #[\Override]
    public function width(): int
    {
        return 0;
    }
}
