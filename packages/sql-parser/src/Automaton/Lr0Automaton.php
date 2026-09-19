<?php

declare(strict_types=1);

namespace SqlParser\Automaton;

/**
 * The states of an LR(0) automaton, their transitions and their completed rules.
 *
 * @visibility root
 */
final class Lr0Automaton
{
    /**
     * @param list<list<int>> $kernels Kernel items of each state, sorted
     * @param list<array<int, int>> $transitions Target state by symbol, per state
     * @param list<list<int>> $reductions Rules completed in each state, in rule order
     */
    public function __construct(
        public readonly array $kernels,
        public readonly array $transitions,
        public readonly array $reductions,
    ) {
    }

    /**
     * Answers how many states there are.
     *
     * @return int State count
     */
    public function stateCount(): int
    {
        return count($this->kernels);
    }

    /**
     * Answers the state reached on a symbol, if any.
     *
     * @param int $state State to leave
     * @param int $symbol Symbol to move on
     *
     * @return int|null The target state, or null when the state has no such transition
     */
    public function transition(int $state, int $symbol): ?int
    {
        return $this->transitions[$state][$symbol] ?? null;
    }
}
