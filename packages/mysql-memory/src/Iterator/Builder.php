<?php

declare(strict_types=1);

namespace MySqlMemory\Iterator;

use MySqlMemory\Iterator\Combine\RecursiveUnionIterator;
use MySqlMemory\Iterator\Combine\SetOperationIterator;
use MySqlMemory\Iterator\Combine\NestedLoopJoinIterator;
use MySqlMemory\Iterator\Transform\MaterializeIterator;
use MySqlMemory\Iterator\Transform\AggregateIterator;
use MySqlMemory\Iterator\Transform\LimitIterator;
use MySqlMemory\Iterator\Transform\SortIterator;
use MySqlMemory\Iterator\Transform\DistinctIterator;
use MySqlMemory\Iterator\Transform\ProjectIterator;
use MySqlMemory\Iterator\Transform\FilterIterator;
use MySqlMemory\Iterator\Source\WorkingTableIterator;
use MySqlMemory\Iterator\Source\ValuesIterator;
use MySqlMemory\Iterator\Source\ZeroRowsIterator;
use MySqlMemory\Iterator\Source\SingleRowIterator;
use MySqlMemory\Iterator\Source\TableScanIterator;
use LogicException;
use MySqlMemory\Plan\Path\AccessPath;
use MySqlMemory\Plan\Path\Transform\Aggregate;
use MySqlMemory\Plan\Path\Transform\Distinct;
use MySqlMemory\Plan\Path\Transform\Filter;
use MySqlMemory\Plan\Path\Transform\Limit;
use MySqlMemory\Plan\Path\Transform\Materialize;
use MySqlMemory\Plan\Path\Combine\NestedLoopJoin;
use MySqlMemory\Plan\Path\Transform\Project;
use MySqlMemory\Plan\Path\Combine\RecursiveUnion;
use MySqlMemory\Plan\Path\Combine\SetOperation;
use MySqlMemory\Plan\Path\Source\SingleRow;
use MySqlMemory\Plan\Path\Transform\Sort;
use MySqlMemory\Plan\Path\Source\TableScan;
use MySqlMemory\Plan\Path\Source\Values;
use MySqlMemory\Plan\Path\Source\WorkingTable;
use MySqlMemory\Plan\Path\Source\ZeroRows;

/**
 * Creates the iterator tree that executes an access path tree.
 *
 * @visibility MySqlMemory
 */
final class Builder
{
    /**
     * @var array<int, TableScanIterator> The iterator of each table scan built, by the object id of its path
     */
    public array $scans = [];

    /**
     * Creates the iterator of a path and, recursively, of its inputs.
     */
    public function build(AccessPath $path): RowIterator
    {
        return match (true) {
            $path instanceof TableScan => $this->scans[spl_object_id($path)] = new TableScanIterator($path),
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
            default => throw new LogicException('No iterator executes ' . $path::class . '.'),
        };
    }
}
