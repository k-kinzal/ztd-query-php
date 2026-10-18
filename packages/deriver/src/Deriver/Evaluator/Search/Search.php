<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Search;

use Closure;
use Deriver\Deriver\Evaluator\Term;
use Fiber;
use LogicException;
use SplQueue;

/**
 * Shares work between live branches and parks parents until their dependencies finish.
 * All scheduling is local to one evaluation; application code is never executed.
 * @visibility parent
 */
final class Search
{
    /**
     * @var SplQueue<Task>
     */
    private readonly SplQueue $pending;
    /**
     * @var array<string, Task>
     */
    private array $dependencies = [];
    private ?Task $current = null;

    /**
     * Creates an empty evaluation queue.
     */
    public function __construct()
    {
        $this->pending = new SplQueue();
    }

    /**
     * Requests a shared dependency without recursively monopolizing the search.
     * @param Closure(): Term $evaluate Dependency evaluator
     * @param Closure(): Term $cycle Residual for a genuine dependency cycle
     */
    public function resolve(string $key, Closure $evaluate, Closure $cycle): Term
    {
        $task = $this->dependencies[$key] ??= $this->enqueue($evaluate);
        $pending = [$task];
        $seen = [];
        while ($pending !== []) {
            $dependency = array_pop($pending);
            if ($dependency->result !== null || isset($seen[spl_object_id($dependency)])) {
                continue;
            }
            if ($dependency === $this->current) {
                return $cycle();
            }
            $seen[spl_object_id($dependency)] = true;
            array_push($pending, ...$dependency->waiting);
        }
        return $this->await($task);
    }

    /**
     * Exposes all sibling branches and registers the entire join before waiting.
     * @param list<Closure(): Term> $branches Independent branch or operand evaluations
     * @return list<Term> Results in source order, independent of completion order
     */
    public function all(array $branches): array
    {
        $tasks = array_map($this->enqueue(...), $branches);
        $parent = $this->current;
        if ($parent !== null) {
            $parent->waiting = $tasks;
        }
        try {
            return array_map($this->await(...), $tasks);
        } finally {
            if ($parent !== null) {
                $parent->waiting = [];
            }
        }
    }

    /**
     * Gives another live branch an opportunity at a bounded unit of analysis work.
     */
    public function checkpoint(): void
    {
        if ($this->current !== null) {
            Fiber::suspend();
        }
    }

    /**
     * @param Closure(): Term $evaluate Deferred work
     */
    public function enqueue(Closure $evaluate): Task
    {
        $task = new Task($evaluate);
        $this->pending->enqueue($task);
        return $task;
    }

    /**
     * Waits without occupying the evaluator while a child is unresolved.
     */
    public function await(Task $task): Term
    {
        $parent = $this->current;
        $waiting = $parent->waiting ?? [];
        if ($parent !== null) {
            $parent->waiting = [...$waiting, $task];
        }
        try {
            while (true) {
                $result = $task->result;
                if ($result !== null) {
                    return $result;
                }
                if ($parent === null) {
                    $this->advance();
                } else {
                    Fiber::suspend();
                }
            }
        } finally {
            if ($parent !== null) {
                $parent->waiting = $waiting;
            }
        }
    }

    /**
     * Advances one runnable branch; waiting parents do not consume analysis work.
     * @throws LogicException If the dependency queue contains no runnable work
     */
    public function advance(): void
    {
        if ($this->pending->isEmpty()) {
            throw new LogicException('Evaluation dependencies have no runnable branch.');
        }
        $task = $this->pending->dequeue();
        $this->current = $task;
        try {
            $task->fiber->isStarted() ? $task->fiber->resume() : $task->fiber->start();
            if ($task->fiber->isTerminated()) {
                $task->result = $task->fiber->getReturn();
                foreach ($task->complete() as $parent) {
                    $this->pending->enqueue($parent);
                }
            } elseif ($task->park()) {
                $this->pending->enqueue($task);
            }
        } finally {
            $this->current = null;
        }
    }

}
