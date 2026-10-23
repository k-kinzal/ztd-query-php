<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Path\Combine;

use MySqlMemory\Plan\Path\AccessPath;
use Override;

/**
 * Reads UNION operands in sequence without materializing their remaining rows.
 *
 * @visibility MySqlMemory
 */
final class UnionStream implements AccessPath
{
    /**
     * @param AccessPath $left The first operand
     * @param AccessPath $right The second operand
     * @param int $columns The number of combined output columns
     * @param bool $retain Whether consumed rows replace the exposed result fields
     * @param bool $prepare Whether a materialized prefix must run even beneath LIMIT 0
     */
    public function __construct(public readonly AccessPath $left, public readonly AccessPath $right, public readonly int $columns, public readonly bool $retain, public readonly bool $prepare = false)
    {
    }

    /**
     * Answers the number of combined columns.
     */
    #[Override]
    public function width(): int
    {
        return $this->columns;
    }
}
