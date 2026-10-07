<?php

declare(strict_types=1);

namespace MySqlMemory\Iterator;

use MySqlMemory\Plan\Path\AccessPath;
use MySqlMemory\Plan\Path\Aggregate;
use MySqlMemory\Plan\Path\Distinct;
use MySqlMemory\Plan\Path\Filter;
use MySqlMemory\Plan\Path\Limit;
use MySqlMemory\Plan\Path\Materialize;
use MySqlMemory\Plan\Path\NestedLoopJoin;
use MySqlMemory\Plan\Path\Project;
use MySqlMemory\Plan\Path\RecursiveUnion;
use MySqlMemory\Plan\Path\WorkingTable;
use MySqlMemory\Plan\Path\SetOperation;
use MySqlMemory\Plan\Path\SingleRow;
use MySqlMemory\Plan\Path\Sort;
use MySqlMemory\Plan\Path\TableScan;
use MySqlMemory\Plan\Path\Values;
use MySqlMemory\Plan\Path\ZeroRows;

/**
 * Creates the iterator tree that executes an access path tree.
 *
 * @visibility MySqlMemory
 */
final class Builder
{
    /**
     * Creates the iterator of a path and, recursively, of its inputs.
     */
    public function build(AccessPath $path): RowIterator
    {
        return match (true) {
            $path instanceof TableScan => new TableScanIterator($path),
            $path instanceof SingleRow => new SingleRowIterator(),
            $path instanceof ZeroRows => new ZeroRowsIterator(),
            $path instanceof Filter => new FilterIterator($path, $this->build($path->input)),
            $path instanceof NestedLoopJoin => new NestedLoopJoinIterator($path, $this->build($path->left), $this->build($path->right)),
            $path instanceof Materialize => new MaterializeIterator($path, $this->build($path->query->root)),
            $path instanceof Aggregate => new AggregateIterator($path, $this->build($path->input)),
            $path instanceof Project => new ProjectIterator($path, $this->build($path->input)),
            $path instanceof Sort => new SortIterator($path, $this->build($path->input)),
            $path instanceof Limit => new LimitIterator($path, $this->build($path->input)),
            $path instanceof Distinct => new DistinctIterator($path, $this->build($path->input)),
            $path instanceof Values => new ValuesIterator($path),
            $path instanceof RecursiveUnion => new RecursiveUnionIterator($path, $this->build($path->anchor), $this->build($path->recursive)),
            $path instanceof WorkingTable => new WorkingTableIterator($path),
            $path instanceof SetOperation => new SetOperationIterator($path, $this->build($path->left), $this->build($path->right)),
            default => throw new \LogicException('No iterator executes ' . $path::class . '.'),
        };
    }
}
