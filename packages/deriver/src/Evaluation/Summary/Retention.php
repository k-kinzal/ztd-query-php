<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Summary;

use Deriver\Evaluation\State;
use Deriver\Value\Term;
use WeakMap;

/**
 * Bounds shared summary retention and removes storage belonging to previous callers.
 * @visibility root
 */
final class Retention
{
    /**
     * Caps retained value graphs independently of PHP's allocator and literal folding.
     * @param CompletionRecord $record Candidate completion
     * @return bool Whether its value graph fits the shared cache
     */
    public function small(CompletionRecord $record): bool
    {
        /** @var WeakMap<Term, true> $seen */
        $seen = new WeakMap();
        $pending = array_values($record->state->memory->cells);
        if ($record->state->completion->value !== null) {
            $pending[] = $record->state->completion->value;
        }
        while ($pending !== []) {
            $value = array_pop($pending);
            if (isset($seen[$value])) {
                continue;
            }
            if (count($seen) + count($value->operands) > 4096) {
                return false;
            }
            $seen[$value] = true;
            array_push($pending, ...array_values($value->operands));
        }
        return true;
    }

    /**
     * Retains the explanation closure of this computation, independently of unrelated caller frontiers.
     * @param list<CompletionRecord> $outcomes Compact isolated completions
     * @param array<string, \Deriver\Result\Derivation> $available Query explanation graph
     * @return array<string, \Deriver\Result\Derivation>|null Bounded explanation closure
     */
    public function evidence(array $outcomes, array $available): ?array
    {
        $pending = [];
        foreach ($outcomes as $outcome) {
            array_push($pending, ...$outcome->state->evidence);
        }
        $result = [];
        while ($pending !== []) {
            $id = array_pop($pending);
            if (isset($result[$id]) || !isset($available[$id])) {
                continue;
            }
            if (count($result) >= 4096) {
                return null;
            }
            $result[$id] = $available[$id];
            array_push($pending, ...$available[$id]->parents);
        }
        return $result;
    }

    /**
     * Keeps completion-local storage, avoiding retention of the original caller's heap and registers.
     * @param CompletionRecord $record Isolated completion
     * @return CompletionRecord Compact, independently replayable record
     */
    public function compact(CompletionRecord $record): CompletionRecord
    {
        $state = new State();
        foreach ($record->state->locals as $name => $location) {
            $state->memory->write($state->local($name), $record->state->memory->read($location));
        }
        $state->completion = $record->state->completion;
        $state->guard = $record->state->guard;
        $state->constraints = $record->state->constraints;
        $state->evidence = $record->state->evidence;
        $state->controls = $record->state->controls;
        return new CompletionRecord($state, $record->allocations);
    }
}
