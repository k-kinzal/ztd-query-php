<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Derivation;

use LogicException;
use SplPriorityQueue;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\Symbol;

/**
 * Orders pending completions by their admissible lower bound and prunes equivalent more expensive states.
 */
final class CompletionFrontier
{
    /**
     * @var SplPriorityQueue<array{int, int}, CompletionState>
     */
    private readonly SplPriorityQueue $queue;
    /**
     * @var array<string, int>
     */
    private array $visited = [];

    /**
     * Binds one bounded search; callers cannot change the queue extraction mode.
     */
    public function __construct(private readonly CompletionCosts $costs, private readonly int $budget, private readonly bool $minimum = true)
    {
        $this->queue = new SplPriorityQueue();
        $this->queue->setExtractFlags(SplPriorityQueue::EXTR_DATA);
    }

    /**
     * Returns and removes the least estimated-cost state, or null when no feasible frontier remains.
     * @phpstan-impure
     * @throws LogicException When the priority queue does not return its configured data payload
     */
    public function take(): ?CompletionState
    {
        if ($this->queue->isEmpty()) {
            return null;
        }
        $state = $this->queue->extract();
        return $state instanceof CompletionState ? $state : throw new LogicException('Invalid completion queue payload.');
    }

    /**
     * Erases emitted terminals only after remembering whether they discharge the output requirement.
     * @param list<Symbol> $symbols
     * @param array<string, int> $occurrences
     */
    public function offer(array $symbols, array $occurrences, bool $nonEmpty, int $spent, ?CompletionState $parent = null): void
    {
        $nonEmpty = $nonEmpty && !$this->costs->hasTerminalOutput($symbols);
        $pending = array_values(array_filter($symbols, static fn (Symbol $symbol): bool => $symbol instanceof NonTerminal));
        $lower = $this->costs->completion(new Production($pending), [], $nonEmpty);
        if ($lower === PHP_INT_MAX || $spent > $this->budget || $lower > $this->budget - $spent) {
            return;
        }
        $state = new CompletionState($pending, $occurrences, $nonEmpty, $spent, $parent);
        $key = $state->key();
        if (($this->visited[$key] ?? PHP_INT_MAX) <= $spent) {
            return;
        }
        $this->visited[$key] = $spent;
        $priority = $this->minimum ? [-($spent + $lower), -$spent] : [-$lower, -$spent];
        $this->queue->insert($state, $priority);
    }
}
