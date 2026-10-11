<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Subquery;

use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Iterator\Builder;
use MySqlMemory\Iterator\RowIterator;
use MySqlMemory\Plan\QueryPlan;

/**
 * The rows of a subquery, computed in a frame inside the frame of the expression that reads them.
 *
 * @visibility MySqlMemory
 */
final class Rows
{
    private ?RowIterator $iterator = null;

    /**
     * @param QueryPlan $plan The plan of the subquery
     */
    public function __construct(public readonly QueryPlan $plan)
    {
    }

    /**
     * Starts the subquery for the row of a frame.
     */
    public function start(Frame $frame): RowIterator
    {
        return $this->in($frame->inner());
    }

    /**
     * Starts the subquery in a caller-owned frame whose final input state can be inspected.
     */
    public function in(Frame $frame): RowIterator
    {
        $this->iterator ??= (new Builder())->build($this->plan->root);
        $this->iterator->init($frame);

        return $this->iterator;
    }
}
