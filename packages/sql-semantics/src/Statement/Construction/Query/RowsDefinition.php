<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Construction\Query;

/**
 * All new VALUES rows, including unequal widths for SQL diagnostics.
 * @visibility public
 * @example Specifying new input before name resolution
 *     $input = new \SqlSemantics\Statement\Construction\Query\RowsDefinition(new \SqlSemantics\Statement\Construction\Query\RowDefinition(new \SqlSemantics\Statement\Expression\NullConstant()));
 *     count($input->rows) // => 1
 */
final class RowsDefinition
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * @var non-empty-list<RowDefinition>
     */
    public readonly array $rows;

    /**
     * Keeps the complete supplied sequence, including duplicate values.
     */
    public function __construct(RowDefinition $first, RowDefinition ...$rest)
    {
        $this->rows = [$first, ...array_values($rest)];
    }
}
