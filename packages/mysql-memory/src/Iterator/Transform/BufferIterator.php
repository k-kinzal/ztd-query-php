<?php

declare(strict_types=1);

namespace MySqlMemory\Iterator\Transform;

use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Iterator\RowIterator;
use Override;

/**
 * Finishes input effects before publishing the stored rows of the same query block.
 *
 * Unlike a derived relation, buffering retains the block's frame and scanned records.
 *
 * @visibility MySqlMemory
 */
final class BufferIterator implements RowIterator
{
    private Frame $frame;

    /**
     * @var list<list<int|float|string|\MySqlMemory\Evaluation\ExactDecimal|null>>
     */
    private array $rows = [];

    /**
     * @var list<array<int, array{\MySqlMemory\Plan\Path\Source\TableScan, list<int|float|string|null>}>>
     */
    private array $records = [];

    private int $next = 0;

    /**
     * @param RowIterator $input The input consumed before any output read
     */
    public function __construct(public readonly RowIterator $input)
    {
    }

    /**
     * Stores all input rows and their scanned table records.
     */
    #[Override]
    public function init(Frame $frame): void
    {
        $this->frame = $frame;
        $this->rows = [];
        $this->records = [];
        $this->next = 0;
        $this->input->init($frame);
        $position = $frame->context->row;
        try {
            while (($row = $this->input->read()) !== null) {
                $this->rows[] = $row;
                $this->records[] = $frame->scanned;
                if ($frame->outer === null) {
                    $frame->context->row = count($this->rows) + 1;
                }
            }
        } finally {
            $frame->context->row = $position;
        }
    }

    /**
     * Reads the next stored row without executing its input expressions again.
     */
    #[Override]
    public function read(): ?array
    {
        $row = $this->rows[$this->next++] ?? null;
        if ($row !== null) {
            $this->frame->buffered = $row;
            $this->frame->scanned = $this->records[$this->next - 1];
            $variables = $this->frame->context->variables;
            foreach ($this->frame->scanned as [$scan, $fields]) {
                $variables->instance->dictionary->cache->buffers->retain($variables, $scan->table, $fields, $scan->handle);
            }
        }

        return $row;
    }
}
