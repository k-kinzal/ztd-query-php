<?php

declare(strict_types=1);

namespace MySqlMemory\Iterator;

use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Plan\Path\SetKind;
use MySqlMemory\Plan\Path\SetOperation;
use MySqlMemory\Value\Order;
use Override;

/**
 * Combines the rows of two queries by UNION, INTERSECT or EXCEPT.
 *
 * Without ALL, the result holds each distinct row once; with ALL, INTERSECT keeps a row as often
 * as both sides hold it and EXCEPT as often as the left side holds it more.
 *
 * @visibility MySqlMemory
 */
final class SetOperationIterator implements RowIterator
{
    /**
     * @var list<list<int|float|string|null>>
     */
    private array $rows = [];

    private int $next = 0;

    /**
     * @param SetOperation $path The path executed
     * @param RowIterator $left The iterator of the left query
     * @param RowIterator $right The iterator of the right query
     */
    public function __construct(public readonly SetOperation $path, public readonly RowIterator $left, public readonly RowIterator $right)
    {
    }

    /**
     * Reads both queries and combines them.
     */
    #[Override]
    public function init(Frame $frame): void
    {
        $left = $this->rows($this->left, new Frame($frame->context, [], $frame->outer));
        $right = $this->rows($this->right, new Frame($frame->context, [], $frame->outer));
        $counts = [];
        foreach ($right as [$key]) {
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }
        $result = [];
        $emitted = [];
        $candidates = $this->path->kind === SetKind::Union ? [...$left, ...$right] : $left;
        foreach ($candidates as [$key, $row]) {
            if ($this->path->kind === SetKind::Intersect || $this->path->kind === SetKind::Except) {
                $present = ($counts[$key] ?? 0) > 0;
                if ($this->path->distinct ? $present === ($this->path->kind === SetKind::Except) : ($this->path->kind === SetKind::Intersect ? !$present : $present)) {
                    if (!$this->path->distinct && $present) {
                        $counts[$key]--;
                    }
                    continue;
                }
                if (!$this->path->distinct && $this->path->kind === SetKind::Intersect) {
                    $counts[$key]--;
                }
            }
            if ($this->path->distinct && isset($emitted[$key])) {
                continue;
            }
            $emitted[$key] = true;
            $result[] = $row;
        }
        $this->rows = $result;
        $this->next = 0;
    }

    /**
     * Reads the rows of one query with their equality keys.
     *
     * @return list<array{string, list<int|float|string|null>}>
     */
    public function rows(RowIterator $iterator, Frame $frame): array
    {
        $iterator->init($frame);
        $rows = [];
        $width = count($this->path->domains);
        while (($row = $iterator->read()) !== null) {
            $row = array_slice($row, 0, $width);
            $key = '';
            foreach ($this->path->domains as $position => $domain) {
                $key .= Order::key($row[$position], $domain) . "\0";
            }
            $rows[] = [$key, $row];
        }

        return $rows;
    }

    /**
     * Answers the next combined row.
     */
    #[Override]
    public function read(): ?array
    {
        return $this->rows[$this->next++] ?? null;
    }
}
