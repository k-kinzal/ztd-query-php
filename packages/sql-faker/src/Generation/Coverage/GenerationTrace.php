<?php

declare(strict_types=1);

namespace SqlFaker\Generation\Coverage;

/**
 * Retains only the latest generation's occurrence paths and diagnostic attempt boundaries.
 *
 * @phpstan-import-type Rewrite from LexicalObservation
 * @phpstan-type Event array{generationId: int, attemptId: int, nodeId: int, parentNodeId: int|null, rhsPosition: int|null, ruleId: string, productionId: string, selectionReason: string}
 * @phpstan-type Attempt array{id: int, events: list<Event>, status: string, error: string|null, sqlHash: string|null}
 * @phpstan-type Trace array{generationId: int, root: string, planSummary: array<string, int|string|bool|null>, attempts: list<Attempt>, lexicalEvents: list<string>, features: array<string, list<string>>, candidateSources: array<int, list<string>>, candidateConditions: array<int, array{left: array{allowed: int, rules: list<string>}|null, boundaries: array<int, array{allowed: int, rules: list<string>}>}>, candidateRejections: list<array{index: int, candidate: string, rules: list<string>}>, rewriteOperations: list<Rewrite>, rewrites: list<string>, spacingEvents: list<array{candidate: string, separator: string, allowed: int, rules: list<string>}>, status: string, reachedIds: list<string>, emittedIds: list<string>}
 * @visibility root
 */
final class GenerationTrace
{
    /**
     * @var array<string, true>
     */
    private array $reached = [];

    /**
     * @var Trace
     */
    public array $value;

    /**
     * Begins a fresh trace without consuming Faker randomness.
     *
     * @param array<string, int|string|bool|null> $plan
     */
    public function __construct(int $id, string $root, array $plan)
    {
        $this->value = ['generationId' => $id, 'root' => $root, 'planSummary' => $plan,
            'attempts' => [], 'features' => [], 'candidateSources' => [], 'candidateConditions' => [], 'candidateRejections' => [], 'rewriteOperations' => [], 'rewrites' => [], 'spacingEvents' => [], 'lexicalEvents' => is_string($plan['lexicalTarget'] ?? null) ? [$plan['lexicalTarget']] : [], 'status' => 'in-progress', 'reachedIds' => [], 'emittedIds' => []];
    }

    /**
     * Opens a diagnostic attempt; the standard generator makes one attempt per call.
     */
    public function beginAttempt(int $id): void
    {
        $this->value['attempts'][] = ['id' => $id, 'events' => [], 'status' => 'in-progress', 'error' => null, 'sqlHash' => null];
    }

    /**
     * Records a selection before its occurrence is expanded.
     */
    public function record(int $node, ?int $parent, ?int $position, string $rule, string $production, string $reason): void
    {
        $index = count($this->value['attempts']) - 1;
        $this->value['attempts'][$index]['events'][] = ['generationId' => $this->value['generationId'],
            'attemptId' => $this->value['attempts'][$index]['id'], 'nodeId' => $node, 'parentNodeId' => $parent, 'rhsPosition' => $position,
            'ruleId' => $rule, 'productionId' => $production, 'selectionReason' => $reason];
        if (!isset($this->reached[$production])) {
            $this->reached[$production] = true;
            $this->value['reachedIds'][] = $production;
        }
    }

    /**
     * Marks a failed attempt without losing its reached paths.
     */
    public function discard(string $error): void
    {
        $index = count($this->value['attempts']) - 1;
        if (!isset($this->value['attempts'][$index])) {
            return;
        }
        $this->value['attempts'][$index]['status'] = 'discarded';
        $this->value['attempts'][$index]['error'] = $error;
    }

    /**
     * Credits emitted productions only to the attempt returning SQL.
     *
     * @param list<int>|null $nodes Source occurrences preserved in the output
     * @return list<string>
     */
    public function commit(string $hash, ?array $nodes = null): array
    {
        $index = count($this->value['attempts']) - 1;
        if (!isset($this->value['attempts'][$index])) {
            return [];
        }
        $this->value['attempts'][$index]['status'] = 'committed';
        $this->value['attempts'][$index]['sqlHash'] = $hash;
        $this->value['status'] = 'success';
        $events = $this->value['attempts'][$index]['events'];
        if ($nodes !== null) {
            $preserved = array_fill_keys($nodes, true);
            $events = array_filter($events, static fn (array $event): bool => isset($preserved[$event['nodeId']]));
        }
        $this->value['emittedIds'] = array_values(array_unique(array_column($events, 'productionId')));
        return $this->value['emittedIds'];
    }

    /**
     * Finalizes failed generations, including failures before lexical realization.
     */
    public function end(): void
    {
        if ($this->value['status'] === 'in-progress') {
            $this->value['status'] = 'failed';
            $index = count($this->value['attempts']) - 1;
            if ($index >= 0 && $this->value['attempts'][$index]['status'] === 'in-progress') {
                $this->discard('Generation terminated before returning SQL.');
            }
        }
    }
}
