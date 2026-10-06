<?php

declare(strict_types=1);

namespace Deriver\Source;

/**
 * Maps byte offsets without rescanning file prefixes for every IR instruction.
 * @visibility root
 */
final class LineMap
{
    /**
     * @var list<int> Beginning byte offset of each source line
     */
    public readonly array $starts;

    /**
     * @param string $contents Captured source bytes
     */
    public function __construct(string $contents)
    {
        $starts = [0];
        $offset = 0;
        while (($newline = strpos($contents, "\n", $offset)) !== false) {
            $offset = $newline + 1;
            $starts[] = $offset;
        }
        $this->starts = $starts;
    }

    /**
     * Finds the one-based byte column with logarithmic work in the file's line count.
     * @param int $offset Nonnegative source byte position
     * @return int One-based byte column
     */
    public function column(int $offset): int
    {
        return $offset - $this->starts[$this->line($offset) - 1] + 1;
    }

    /**
     * Finds the one-based source line from a byte offset.
     */
    public function line(int $offset): int
    {
        $low = 0;
        $high = count($this->starts) - 1;
        while ($low < $high) {
            $middle = intdiv($low + $high + 1, 2);
            if ($this->starts[$middle] <= $offset) {
                $low = $middle;
            } else {
                $high = $middle - 1;
            }
        }
        return $low + 1;
    }
}
