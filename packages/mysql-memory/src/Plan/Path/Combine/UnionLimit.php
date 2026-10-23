<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Path\Combine;

use MySqlMemory\Plan\Path\AccessPath;
use Override;

/**
 * Bounds a streamed legacy UNION before its operands evaluate visible expressions.
 *
 * @visibility MySqlMemory
 */
final class UnionLimit implements AccessPath
{
    /**
     * @param AccessPath $input The streamed UNION operands
     * @param int|null $count The visible row bound
     * @param int $offset The input rows skipped before projection
     * @param bool $calculate Whether remaining table rows must be counted
     */
    public function __construct(public readonly AccessPath $input, public readonly ?int $count, public readonly int $offset, public readonly bool $calculate)
    {
    }

    /**
     * Answers the combined output width.
     */
    #[Override]
    public function width(): int
    {
        return $this->input->width();
    }
}
