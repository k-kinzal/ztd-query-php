<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Path;

/**
 * The rows a recursive common table expression produced in its last iteration, read by its own references.
 *
 * @visibility MySqlMemory
 */
final class WorkingTable implements AccessPath
{
    /**
     * @var list<list<int|float|string|null>> The rows of the last iteration
     */
    public array $rows = [];

    /**
     * @param int $width The number of columns
     */
    public function __construct(public readonly int $width)
    {
    }

    /**
     * Answers the number of columns.
     */
    #[\Override]
    public function width(): int
    {
        return $this->width;
    }
}
