<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Summary;

use Deriver\Evaluation\Context;
use Deriver\Evaluation\Demand\Cell;
use Deriver\Evaluation\State;
use Deriver\Result\Derivation;

/**
 * Bounded session reuse of closed, isolated function completions across observation queries.
 * @visibility root
 */
final class SharedSummaries
{
    /**
     * @var array<string, array{outcomes: list<CompletionRecord>, cost: int, evidence: array<string, Derivation>, graphs: array<string, true>}> Closed specializations
     */
    private array $entries = [];

    /**
     * Replays only when the same logical work still fits the requesting query's budget.
     * @param string $key Budget-specific invocation identity
     * @param Context $context Requesting query
     * @param State $entry Fresh isolated entry
     * @return list<State>|null Rebased completions or a cache miss
     */
    public function replay(string $key, Context $context, State $entry): ?array
    {
        $record = $this->entries[$key] ?? null;
        if ($record === null || $context->sealed || $context->transfers + $record['cost'] > $context->query->budget()->transfers || count($context->evidence + $record['evidence']) >= $context->query->budget()->nodes) {
            return null;
        }
        $context->transfers += $record['cost'];
        $context->evidence += $record['evidence'];
        $context->graphs += $record['graphs'];
        $context->summaries->hits++;
        return array_map(static fn (CompletionRecord $outcome): State => $outcome->instantiate($entry), $record['outcomes']);
    }

    /**
     * Publishes small closed computations only; interrupted and context-dependent paths never enter the shared cache.
     * @param string $key Budget-specific invocation identity
     * @param Context $context Completed query context
     * @param Cell $cell Stable isolated computation
     */
    public function remember(string $key, Context $context, Cell $cell): void
    {
        if ($context->frontiers !== [] || $context->sealed || $cell->status !== 'stable' || $cell->entry->guard !== [] || $cell->entry->controls !== [] || count($cell->outcomes) > 32 || count($context->evidence) > 4096) {
            return;
        }
        $outcomes = [];
        foreach ($cell->outcomes as $outcome) {
            if ($outcome->havoc || count($outcome->state->memory->cells) > 256 || !(new Retention())->small($outcome)) {
                return;
            }
            $outcomes[] = (new Retention())->compact($outcome);
        }
        if (count($this->entries) >= 32) {
            array_shift($this->entries);
        }
        $this->entries[$key] = ['outcomes' => $outcomes, 'cost' => $cell->cost, 'evidence' => $context->evidence, 'graphs' => $context->graphs];
    }
}
