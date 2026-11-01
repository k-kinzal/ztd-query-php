<?php

declare(strict_types=1);

namespace MySqlMemory\Plan;

use MySqlMemory\Plan\Path\AccessPath;
use MySqlMemory\Typing\Domain;

/**
 * The plan of a query: the access path that computes its rows, and the name and domain of each output column.
 *
 * The rows of the root may hold more values than the output columns; the leading ones are the output.
 *
 * @visibility MySqlMemory
 */
final class QueryPlan
{
    /**
     * @param AccessPath $root The access path of the rows
     * @param list<Domain> $domains The domain of each output column
     * @param list<string> $names The name of each output column
     * @param list<ColumnOrigin|null> $origins The base column each output column reads directly, if any
     */
    public function __construct(public readonly AccessPath $root, public readonly array $domains, public readonly array $names, public readonly array $origins = [])
    {
    }
}
