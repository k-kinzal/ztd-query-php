<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Summary;

use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\CallableIdentity;
use Deriver\Evaluation\Control\ResidualPaths;
use Deriver\Evaluation\Demand\Cell;
use Deriver\Evaluation\Demand\Components;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\State;

/**
 * Solves reusable isolated call demands with monotone cyclic approximations.
 * @visibility root
 */
final class Evaluation
{
    /**
     * @param Machine $machine Shared instruction evaluator
     */
    public function __construct(public readonly Machine $machine)
    {
    }

    /**
     * Reuses a closed specialization or evaluates its dynamically discovered dependencies.
     * @param CallableGraph $body Callable graph
     * @param State $entry Bound inputs
     * @return list<State> Inclusive correlated completions
     */
    public function run(CallableGraph $body, State $entry): array
    {
        $context = $this->machine->context;
        $table = $context->summaries;
        if (!$this->eligible($body, $entry)) {
            return $this->machine->execute($body, $entry);
        }
        $key = (new Invocation())->key($body, $entry, $table->history);
        if (!isset($table->cells[$key->id()]) && ($table->contexts($body->symbol) >= 32 || count($table->cells) >= $context->query->budget()->nodes)) {
            $context->frontier('CORRELATION_RELAXED', $body->source, 'specialization-limit');
            return (new ResidualPaths($context))->seal($entry, $body->source, 'specialization-limit');
        }
        $sharedKey = $key->id() . ':' . hash('sha256', serialize($context->query->budget()));
        if ($table->stack === [] && $entry->guard === [] && $entry->controls === []) {
            $shared = $context->shared->replay($sharedKey, $context, $entry);
            if ($shared !== null) {
                return $shared;
            }
        }
        $cell = $table->register($key, $body, $entry);
        if ($cell->status === 'running' || ($table->solving && $cell->status === 'pending')) {
            return $this->replay($cell, $entry);
        }
        if ($cell->status === 'stable' && !$context->sealed && $context->transfers + $cell->cost <= $context->query->budget()->transfers) {
            $context->transfers += $cell->cost;
            $table->hits++;
            return $this->replay($cell, $entry);
        }
        if ($cell->status === 'frontier') {
            return $this->replay($cell, $entry);
        }
        $this->evaluate($cell);
        if ($table->stack === [] && !$table->solving) {
            $this->close();
        }
        $context->shared->remember($sharedKey, $context, $cell);
        return $this->replay($cell, $entry);
    }

    /**
     * Requires a closed source isolation proof and reference-free bound inputs.
     * @param CallableGraph $body Candidate callable
     * @param State $entry Invocation inputs
     * @return bool Whether this implementation can safely reuse the state transformer
     */
    public function eligible(CallableGraph $body, State $entry): bool
    {
        $context = $this->machine->context;
        $proof = new Isolation($context);
        if (!$proof->local($body)) {
            return false;
        }
        $key = (new CallableIdentity())->key($body->symbol);
        $context->summaries->isolated[$key] ??= $proof->callable($body);
        if (!$context->summaries->isolated[$key]) {
            return false;
        }
        foreach ($entry->locals as $location) {
            if (!$proof->value($entry->memory->read($location))) {
                return false;
            }
        }
        return true;
    }

    /**
     * Performs one abstract update without confusing an empty approximation with closure.
     * @param Cell $cell Pending computation
     * @return bool Whether the outcome approximation grew
     */
    public function evaluate(Cell $cell): bool
    {
        $context = $this->machine->context;
        $table = $context->summaries;
        if ($context->sealed || $cell->updates >= $context->query->budget()->recursion) {
            $this->seal($cell);
            return true;
        }
        $cell->status = 'running';
        $cell->updates++;
        $table->stack[] = $cell->key->id();
        $history = $table->history;
        $table->history = $cell->history;
        $start = $context->transfers;
        $paths = $this->machine->execute($cell->body, $cell->entry->fork());
        $cell->cost = $context->transfers - $start;
        $table->history = $history;
        array_pop($table->stack);
        $before = count($cell->outcomes);
        $havoc = $context->sealed;
        foreach ($context->frontiers as $frontier) {
            $havoc = $havoc || $frontier->code === 'BUDGET_EXCEEDED';
        }
        foreach ($paths as $path) {
            $record = new CompletionRecord($path->fork(), max(0, $path->memory->sequence - $cell->entry->memory->sequence), $havoc);
            $cell->outcomes[$record->id()] ??= $record;
        }
        $changed = count($cell->outcomes) !== $before;
        $cell->status = $table->ready($cell) ? 'stable' : 'pending';
        if ($havoc || count($cell->outcomes) > $context->query->budget()->partitions) {
            $this->seal($cell);
            return true;
        }
        if ($changed) {
            $table->invalidate($cell);
        }
        return $changed;
    }

    /**
     * Iterates pending components until a whole pass adds no outcomes or dependencies.
     * Every admitted cell is stable or sealed before observations leave the solver.
     */
    public function close(): void
    {
        $table = $this->machine->context->summaries;
        $table->solving = true;
        do {
            $changed = false;
            $count = count($table->cells);
            foreach ((new Components())->find($table) as $component) {
                foreach ($component as $id) {
                    $cell = $table->cells[$id];
                    if ($cell->status === 'pending') {
                        $changed = $this->evaluate($cell) || $changed;
                    }
                }
            }
            $changed = $changed || count($table->cells) !== $count;
            if (!$changed) {
                foreach ($table->cells as $cell) {
                    if ($cell->status === 'pending' && $cell->outcomes === []) {
                        $this->seal($cell);
                        $changed = true;
                    }
                }
            }
        } while ($changed);
        foreach ($table->cells as $cell) {
            if ($cell->status === 'pending') {
                $cell->status = 'stable';
            }
        }
        $table->solving = false;
    }

    /**
     * Replays each outcome onto fresh local storage while preserving branch correlation.
     * @param Cell $cell Available approximation
     * @param State $entry Current invocation
     * @return list<State> Independent states
     */
    public function replay(Cell $cell, State $entry): array
    {
        return array_values(array_map(static fn (CompletionRecord $record): State => $record->instantiate($entry), $cell->outcomes));
    }

    /**
     * Permanently replaces an unfinished component with inclusive residual effects.
     * @param Cell $cell Interrupted computation
     */
    public function seal(Cell $cell): void
    {
        $cell->outcomes = array_slice($cell->outcomes, 0, $this->machine->context->query->budget()->partitions, true);
        foreach ((new ResidualPaths($this->machine->context))->seal($cell->entry->fork(), $cell->body->source, 'summary-fixed-point') as $path) {
            $record = new CompletionRecord($path, 0, true);
            $cell->outcomes[$record->id()] = $record;
        }
        $cell->status = 'frontier';
        $this->machine->context->summaries->invalidate($cell);
    }
}
