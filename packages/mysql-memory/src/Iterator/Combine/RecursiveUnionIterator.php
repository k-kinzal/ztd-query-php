<?php

declare(strict_types=1);

namespace MySqlMemory\Iterator\Combine;

use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Iterator\RowIterator;
use MySqlMemory\Plan\Path\Combine\RecursiveUnion;
use MySqlMemory\Value\Order;
use Override;

/**
 * Iterates a recursive common table expression to its fixed point.
 *
 * @visibility MySqlMemory
 */
final class RecursiveUnionIterator implements RowIterator
{
    /**
     * @var list<list<int|float|string|null>>
     */
    private array $rows = [];

    private int $next = 0;

    /**
     * @param RecursiveUnion $path The path executed
     * @param RowIterator $anchor The iterator of the nonrecursive part
     * @param RowIterator $recursive The iterator of the recursive part
     */
    public function __construct(public readonly RecursiveUnion $path, public readonly RowIterator $anchor, public readonly RowIterator $recursive)
    {
    }

    /**
     * Computes every row.
     */
    #[Override]
    public function init(Frame $frame): void
    {
        $seen = [];
        $this->rows = [];
        $fresh = $this->collect($this->anchor, $frame, $seen);
        for ($iteration = 0; $fresh !== []; $iteration++) {
            array_push($this->rows, ...$fresh);
            if ($iteration >= $this->path->limit) {
                throw QueryError::RecursionLimit->error($iteration + 1);
            }
            $this->path->working->rows = $fresh;
            $fresh = $this->collect($this->recursive, $frame, $seen);
        }
        $this->next = 0;
    }

    /**
     * Reads the rows of one part that were not produced before, under UNION DISTINCT.
     *
     * @param array<string, true> $seen
     * @return list<list<int|float|string|null>>
     */
    public function collect(RowIterator $part, Frame $frame, array &$seen): array
    {
        $part->init(new Frame($frame->context, [], $frame->outer));
        $width = count($this->path->domains);
        $rows = [];
        while (($row = $part->read()) !== null) {
            $row = array_slice($row, 0, $width);
            if ($this->path->distinct) {
                $key = '';
                foreach ($this->path->domains as $position => $domain) {
                    $key .= Order::key($row[$position], $domain) . "\0";
                }
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Answers the next row.
     */
    #[Override]
    public function read(): ?array
    {
        return $this->rows[$this->next++] ?? null;
    }
}
