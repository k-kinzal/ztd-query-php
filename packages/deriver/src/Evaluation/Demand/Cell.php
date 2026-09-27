<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Demand;

use Deriver\ControlFlow\CallableGraph;
use Deriver\Evaluation\State;
use Deriver\Evaluation\Summary\CompletionRecord;

/**
 * Separates computation progress from the current, possibly empty approximation.
 * @visibility root
 */
final class Cell
{
    /**
     * unseen, pending, running, stable, or frontier; empty outcomes alone mean none of these.
     */
    public string $status = 'pending';
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
     * @param Key $key Complete demand identity
     * @param CallableGraph $body Immutable graph
     * @param State $entry Frozen bound entry
     * @param list<string> $history Bounded call-site history at registration
     */
    public function __construct(public readonly Key $key, public readonly CallableGraph $body, public readonly State $entry, public readonly array $history = [])
    {
    }
}
