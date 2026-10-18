<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution\Demand;

use Deriver\Deriver\Evaluator\Execution\State;
use Deriver\Deriver\Evaluator\Execution\Summary\CompletionRecord;
use Deriver\Deriver\Lowerer\Graph\CallableGraph;

/**
 * Separates computation progress from the current, possibly empty approximation.
 * @visibility Deriver\Deriver\Evaluator
 * @visibility Deriver\Deriver\Evaluator\Execution
 */
final class Cell
{
    /**
     * Pending, running, stable, or frontier; empty outcomes alone mean none of these.
     */
    public DemandStatus $status = DemandStatus::Pending;
    /**
     * @var array<string, CompletionRecord> Monotone correlated completion approximation
     */
    public array $outcomes = [];
    /**
     * @var array<string, true> Demands read by this cell, including cyclic edges
     */
    public array $dependencies = [];
    /**
     * @var array<string, true> Cells to reconsider after this approximation grows
     */
    public array $dependents = [];
    /**
     * Transfer work required by the most recent evaluation.
     */
    public int $cost = 0;
    /**
     * Number of abstract evaluations of this specialization.
     */
    public int $updates = 0;
    /**
     * Whether every evaluation of this cell completed without introducing a frontier.
     */
    public bool $closed = true;

    /**
     * @param Key $key Complete demand identity
     * @param CallableGraph $body Immutable graph
     * @param State $entry Frozen bound entry
     * @param list<string> $history Bounded call-site history at registration
     */
    public function __construct(public readonly Key $key, public readonly CallableGraph $body, public readonly State $entry, public readonly array $history = [])
    {
    }
}
