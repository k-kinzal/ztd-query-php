<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution\Summary;

use Deriver\Deriver\Evaluator\Execution\Control\ResidualPaths;
use Deriver\Deriver\Evaluator\Execution\Demand\Cell;
use Deriver\Deriver\Evaluator\Execution\Demand\Components;
use Deriver\Deriver\Evaluator\Execution\Evaluator;
use Deriver\Deriver\Evaluator\Execution\State;
use Deriver\Deriver\Lowerer\Declaration\CallableIdentity;
use Deriver\Deriver\Lowerer\Graph\CallableGraph;

/**
 * Solves reusable isolated call demands with monotone cyclic approximations.
 * @visibility Deriver\Deriver\Evaluator
 * @visibility Deriver\Deriver\Evaluator\Execution
 */
final class Evaluation
{
    /**
     * @param Evaluator $machine Shared instruction evaluator
     */
    public function __construct(public readonly Evaluator $machine)
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
        $cell = $table->register($key, $body, $entry);
        if ($cell->status === \Deriver\Deriver\Evaluator\Execution\Demand\DemandStatus::Running || ($table->solving && $cell->status === \Deriver\Deriver\Evaluator\Execution\Demand\DemandStatus::Pending)) {
            return $this->replay($cell, $entry);
        }
        if ($cell->status === \Deriver\Deriver\Evaluator\Execution\Demand\DemandStatus::Stable && !$context->sealed && $context->transfers + $cell->cost <= $context->query->budget()->transfers) {
            $context->transfers += $cell->cost;
            $table->hits++;
            return $this->replay($cell, $entry);
        }
        if ($cell->status === \Deriver\Deriver\Evaluator\Execution\Demand\DemandStatus::Frontier) {
            return $this->replay($cell, $entry);
        }
        $this->evaluate($cell);
        if ($table->stack === [] && !$table->solving) {
            $this->close();
        }
        return $this->replay($cell, $entry);
    }

    /**
     * Requires a closed source isolation proof and reference-free bound inputs.
     * @param CallableGraph $body Candidate callable
     * @param State $entry \Deriver\Deriver\Evaluator\Execution\Summary\Invocation inputs
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
        $cell->status = \Deriver\Deriver\Evaluator\Execution\Demand\DemandStatus::Running;
        $cell->updates++;
        $table->stack[] = $cell->key->id();
        $history = $table->history;
        $table->history = $cell->history;
        $start = $context->transfers;
        $frontiers = $context->frontiers;
        $paths = $this->machine->execute($cell->body, $cell->entry->fork());
        $cell->cost = $context->transfers - $start;
        $table->history = $history;
        array_pop($table->stack);
        $before = count($cell->outcomes);
        $havoc = $context->sealed;
        foreach ($context->frontiers as $id => $frontier) {
            if (($frontiers[$id] ?? null) !== $frontier) {
                $cell->closed = false;
            }
            $havoc = $havoc || $frontier->code === 'BUDGET_EXCEEDED';
        }
        foreach ($paths as $path) {
            $record = new CompletionRecord($path->fork(), max(0, $path->memory->sequence - $cell->entry->memory->sequence), $havoc);
            $cell->outcomes[$record->id()] ??= $record;
        }
        $changed = count($cell->outcomes) !== $before;
        $cell->status = $table->ready($cell) ? \Deriver\Deriver\Evaluator\Execution\Demand\DemandStatus::Stable : \Deriver\Deriver\Evaluator\Execution\Demand\DemandStatus::Pending;
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
                    if ($cell->status === \Deriver\Deriver\Evaluator\Execution\Demand\DemandStatus::Pending) {
                        $changed = $this->evaluate($cell) || $changed;
                    }
                }
            }
            $changed = $changed || count($table->cells) !== $count;
            if (!$changed) {
                foreach ($table->cells as $cell) {
                    if ($cell->status === \Deriver\Deriver\Evaluator\Execution\Demand\DemandStatus::Pending && $cell->outcomes === []) {
                        $this->seal($cell);
                        $changed = true;
                    }
                }
            }
        } while ($changed);
        foreach ($table->cells as $cell) {
            if ($cell->status === \Deriver\Deriver\Evaluator\Execution\Demand\DemandStatus::Pending) {
                $cell->status = \Deriver\Deriver\Evaluator\Execution\Demand\DemandStatus::Stable;
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
        $cell->status = \Deriver\Deriver\Evaluator\Execution\Demand\DemandStatus::Frontier;
        $this->machine->context->summaries->invalidate($cell);
    }
}
