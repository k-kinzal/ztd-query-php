<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Path\Source;

use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Plan\Path\AccessPath;
use MySqlMemory\Value\Json\JsonPath;
use Override;

/**
 * Produces the rows of JSON_TABLE: one for each value the row path selects in the document, and for each row of its nested paths.
 *
 * The document is evaluated in the frame the path is started in, which holds the row of the
 * tables before it, so it may read their columns.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/json-table-functions.html.
 *
 * @visibility MySqlMemory
 */
final class JsonTableScan implements AccessPath
{
    /**
     * @param Evaluable $document The document
     * @param JsonPath $path The row path
     * @param list<JsonColumn> $columns The columns in written order
     */
    public function __construct(public readonly Evaluable $document, public readonly JsonPath $path, public readonly array $columns)
    {
    }

    /**
     * Answers the number of columns, nested columns flattened.
     */
    #[Override]
    public function width(): int
    {
        return array_sum(array_map(static fn (JsonColumn $column): int => $column->width(), $this->columns));
    }
}
