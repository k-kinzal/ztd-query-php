<?php

declare(strict_types=1);

namespace MySqlMemory\Iterator\Source;

use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Iterator\RowIterator;
use MySqlMemory\Plan\Path\Source\TableScan;
use MySqlMemory\Storage\ClusterOrder;
use MySqlMemory\Storage\TimestampZones;
use Override;

/**
 * Reads the rows of a table in clustered index order, as they were when the scan started.
 *
 * A consistent read sees the rows the transaction of the session sees; a locking read goes
 * through the latest rows ({@see \MySqlMemory\Session\Transaction\RowAccess::rows()}).
 *
 * @visibility MySqlMemory
 */
final class TableScanIterator implements RowIterator
{
    private Frame $frame;

    /**
     * @var list<list<int|float|string|null>>
     */
    private array $rows = [];

    /** @var array<int, list<int|float|string|null>> The same rows in storage time zones */
    private array $stored = [];

    private int $next = 0;

    private bool $found = false;

    /**
     * @var list<int> The row numbers, in the order of the rows
     */
    private array $numbers = [];

    /**
     * The number of the row read last, or null before the first and after the last.
     */
    public ?int $current = null;

    /**
     * The lock a locking read of the table takes, which reads the latest rows, or null for a consistent read.
     */
    public ?\MySqlMemory\Concurrency\LockMode $locking = null;

    /**
     * @param TableScan $path The scan executed
     * @param \MySqlMemory\Plan\Path\Source\KeyLookup|null $lookup An optional complete primary-key restriction
     */
    public function __construct(public readonly TableScan $path, public readonly ?\MySqlMemory\Plan\Path\Source\KeyLookup $lookup = null)
    {
    }

    /**
     * Takes the rows of the table in order.
     */
    #[Override]
    public function init(Frame $frame): void
    {
        $this->frame = $frame;
        $variables = $frame->context->variables;
        $ordered = (new ClusterOrder())->rows($this->path->table, $variables->instance->transactions->of($variables->connection)?->access->rows($this->path->table, $this->locking));
        $partitioning = $this->path->table->definition->partitioning;
        if ($partitioning !== null && ($this->path->partitions !== null || $partitioning->method !== \MySqlMemory\Dictionary\Partition\PartitionMethod::Key)) {
            $ordered = (new \MySqlMemory\Storage\Partitions($this->path->table->definition, $partitioning, $frame->context))->ordered($ordered, $this->path->partitions);
        }
        $this->stored = $ordered;
        $rows = (new TimestampZones())->rows($this->path->table, $ordered, $frame->context);
        $this->numbers = array_keys($rows);
        $this->rows = array_values($rows);
        $this->next = 0;
        $this->found = false;
        $this->current = null;
    }

    /**
     * Answers the next row.
     */
    #[Override]
    public function read(): ?array
    {
        while (($row = $this->rows[$this->next] ?? null) !== null) {
            $this->current = $this->numbers[$this->next++];
            if ($this->lookup === null || $this->matches($row)) {
                if ($this->lookup !== null) {
                    $this->next = count($this->rows);
                }

                $this->found = true;
                $this->frame->scanned[spl_object_id($this->path)] = [$this->path, $this->stored[$this->current]];
                $variables = $this->frame->context->variables;
                $variables->instance->dictionary->cache->buffers->retain($variables, $this->path->table, $this->stored[$this->current], $this->path->handle);

                return $row;
            }
        }
        $this->current = null;
        if ($this->lookup !== null && !$this->found) {
            $variables = $this->frame->context->variables;
            $variables->instance->dictionary->cache->buffers->missing($variables, $this->path->table, $this->path->handle);
            $this->frame->scanned[spl_object_id($this->path)] = [$this->path, array_fill(0, $this->path->width(), null)];
        }

        return null;
    }

    /**
     * Tests the complete key without exposing noncandidate rows to the enclosing query.
     *
     * @param list<int|float|string|null> $row The candidate row
     */
    public function matches(array $row): bool
    {
        foreach ($this->lookup->bindings ?? [] as [$position, $value, $comparator]) {
            $key = $value->evaluate($this->frame);
            if ($row[$position] === null || $key === null || $comparator->compare($row[$position], $key, $this->frame->context) !== 0) {
                return false;
            }
        }

        return true;
    }
}
