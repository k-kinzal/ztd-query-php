<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Search;

use Closure;
use Deriver\Deriver\Evaluator\Term;
use Fiber;

/**
 * One resumable dependency evaluation, including every result it currently awaits.
 * @visibility parent
 */
final class Task
{
    /**
     * @var Fiber<void, void, null, Term>
     */
    public readonly Fiber $fiber;
    /**
     * Dependencies whose results are required before resuming this task.
     * @var list<self>
     */
    public array $waiting = [];
    /**
     * @var array<int, self> Parents to notify when this task completes
     */
    public array $waiters = [];
    /**
     * Number of distinct unfinished dependencies registered by the scheduler.
     */
    public int $unresolved = 0;
    /**
     * Completed value, including an unresolved value when evaluation cannot proceed.
     */
    public ?Term $result = null;

    /**
     * @param Closure(): Term $evaluate One dependency or branch
     */
    public function __construct(Closure $evaluate)
    {
        $this->fiber = new Fiber($evaluate);
    }

    /**
     * Subscribes once to each unfinished dependency; returns whether this task can run.
     */
    public function park(): bool
    {
        $id = spl_object_id($this);
        foreach ($this->waiting as $dependency) {
            if ($dependency->result === null && !isset($dependency->waiters[$id])) {
                $dependency->waiters[$id] = $this;
                $this->unresolved++;
            }
        }
        return $this->unresolved === 0;
    }

    /**
     * Releases parents only after their entire dependency join has finished.
     * @return list<self> Newly runnable parents
     */
    public function complete(): array
    {
        $ready = [];
        foreach ($this->waiters as $parent) {
            $parent->unresolved--;
            if ($parent->unresolved === 0) {
                $ready[] = $parent;
            }
        }
        $this->waiters = [];
        return $ready;
    }
}
