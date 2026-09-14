<?php

declare(strict_types=1);

namespace ZtdQuery\Schema;

/**
 * Immutable snapshot of rows received from a driver or fixture provider.
 *
 * The core preserves column values until a platform interprets them.
 *
 * @phpstan-import-type Row from TableDefinition
 */
final class RowSet
{
    /**
     * @param array<int, Row> $rows Rows with their original keys and values.
     */
    public function __construct(public readonly array $rows = [])
    {
    }
}
