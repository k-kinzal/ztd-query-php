<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Demand;

use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\CallableIdentity;
use Deriver\Evaluation\State;

/**
 * Query-local dynamic dependency graph and deterministic specialization registry.
 * @visibility root
 */
final class Table
{
    /**
     * @var array<string, Cell> Registered computations, absent entries are unseen
     */
    public array $cells = [];
    /**
     * @var array<string, int> Number of admitted specializations per callable
     */
    public array $owners = [];
    /**
     * @var list<string> Current evaluation stack
     */
    public array $stack = [];
    /**
     * @var array<string, bool> Closed source isolation proofs
     */
    public array $isolated = [];
    /**
     * @var list<string> Current call-site history
     */
    public array $history = [];
    /**
     * Number of completed summary replays.
     */
    public int $hits = 0;
    /**
     * Whether the outer demand is closing a mutually dependent component.
     */
    public bool $solving = false;

    /**
     * Registers a pending computation and links the currently evaluating dependent.
     * @param Key $key Demand identity
     * @param CallableGraph $body Demanded graph
     * @param State $entry Bound input state
     * @return Cell Existing or newly pending cell
     */
    public function register(Key $key, CallableGraph $body, State $entry): Cell
    {
        $id = $key->id();
        if (!isset($this->cells[$id])) {
            $this->owners[$key->owner] = ($this->owners[$key->owner] ?? 0) + 1;
        }
        $cell = $this->cells[$id] ??= new Cell($key, $body, $entry->fork(), array_slice($this->history, -2));
        $parent = end($this->stack);
        if ($parent !== false) {
            $this->cells[$parent]->dependencies[$id] = true;
            $cell->dependents[$parent] = true;
        }
        return $cell;
    }

    /**
     * Counts contexts before admitting an additional specialization.
     * @param string $owner Callable identity
     * @return int Existing context count
     */
    public function contexts(string $owner): int
    {
        return $this->owners[(new CallableIdentity())->key($owner)] ?? 0;
    }

    /**
     * Schedules every affected completed dependent after an approximation grows.
     * @param Cell $cell Changed computation
     */
    public function invalidate(Cell $cell): void
    {
        foreach ($cell->dependents as $id => $_) {
            if ($this->cells[$id]->status === 'stable') {
                $this->cells[$id]->status = 'pending';
            }
        }
    }

    /**
     * Tests whether a computation can close outside a mutually dependent component.
     * @param Cell $cell Evaluated computation
     * @return bool Whether every dependency has a completed approximation
     */
    public function ready(Cell $cell): bool
    {
        foreach ($cell->dependencies as $id => $_) {
            if (!in_array($this->cells[$id]->status, ['stable', 'frontier'], true)) {
                return false;
            }
        }
        return true;
    }
}
