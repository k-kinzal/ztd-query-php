<?php

declare(strict_types=1);

namespace Deriver\Analysis\Candidates;

use Deriver\Evaluation\Candidate\Choices;
use Deriver\Evaluation\Candidate\Context;
use Deriver\Project\ProjectSnapshot;
use Deriver\Query\Query;
use Deriver\Reference\ResultRef;
use Deriver\Reference\SourceRef;
use Deriver\Result\Alternative;
use Deriver\Result\Assessment;
use Deriver\Result\DerivationResult;
use Deriver\Result\Exceptional;
use Deriver\Result\Frontier;
use Deriver\Result\Serialization\QueryEncoding;
use Deriver\Result\Statistics;
use Deriver\Value\Identity;
use Deriver\Value\Term;
use JsonException;
use WeakMap;

/**
 * Reports candidate multiplicity, residual dependencies, and stopped expansion separately.
 * @visibility root
 */
final class ResultBuilder
{
    /**
     * Adapts the candidate graph while retaining all residual dependencies.
     * @throws JsonException If result metadata cannot be encoded
     */
    public function build(Query $query, Term $graph, Context $context, ProjectSnapshot $snapshot, float $start): DerivationResult
    {
        $normal = [];
        $exceptional = [];
        $concrete = true;
        $alternatives = (new Choices())->alternatives($graph);
        foreach ($alternatives as [$tuple, $guard]) {
            $values = [];
            foreach ($tuple->kind === 'tuple' ? $tuple->operands : ['candidates' => $tuple] as $name => $value) {
                $values[(string) $name] = $value;
            }
            $errors = array_filter($values, static fn (Term $value): bool => $value->kind === 'throwable');
            if ($errors !== []) {
                $exceptional[] = new Exceptional(reset($errors), $guard);
                continue;
            }
            foreach ($values as $value) {
                $concrete = $concrete && $value->isConcrete();
            }
            $identity = count($alternatives) === 1 ? 'single' : (new Identity())->key($tuple);
            $previous = $normal[$identity] ?? null;
            $common = $previous === null ? $guard : array_intersect_assoc($previous->guard, $guard);
            $normal[$identity] = new Alternative($values, $common);
        }
        $frontiers = $this->frontiers($graph, $snapshot);
        $stopped = array_intersect(array_column($frontiers, 'code'), ['DEPTH_LIMIT', 'BUDGET_EXCEEDED', 'MEMORY_LIMIT', 'TIME_LIMIT', 'CANCELLED', 'STACK_LIMIT', 'ENUMERATION_LIMIT', 'CYCLE']) !== [];
        $assessment = new Assessment($stopped ? 'open' : 'closed', 'exact-symbolic', 'preserved', 'source-candidates', $concrete ? 'finite-exhaustive' : 'not-enumerated');
        $key = hash('sha256', $snapshot->id . ':candidates:' . (new QueryEncoding())->key($query));
        $statistics = new Statistics($context->constructedNodes, $context->bodyExpansions, $context->sharedNodeHits, microtime(true) - $start, memory_get_peak_usage(true), $context->referenceExpansions, $context->bodyExpansions, $context->modelApplications, $context->sharedNodeHits, $context->constructedNodes, $context->cache->count(), $context->bodies, $context->references);
        return new DerivationResult(new ResultRef($key), $snapshot->id, $query, array_values($normal), $exceptional, 'not-assessed', $assessment, $frontiers, ['candidate-scope:captured-sources-and-models', 'reachability:not-required'], $context->evidence, $statistics, $context->index->program->diagnostics(), 'candidates', $graph);
    }

    /**

     * @return list<Frontier>

     */
    public function frontiers(Term $graph, ProjectSnapshot $snapshot): array
    {
        $pending = [$graph];
        $seen = new WeakMap();
        $frontiers = [];
        while ($pending !== []) {
            $node = array_pop($pending);
            if (isset($seen[$node])) {
                continue;
            }
            $seen[$node] = true;
            array_push($pending, ...array_values($node->operands));
            $reason = $node->attributes['reason'] ?? null;
            if (!is_string($reason)) {
                continue;
            }
            $source = new SourceRef($snapshot->id, (string) ($node->attributes['source'] ?? ''), (int) ($node->attributes['start'] ?? 0), (int) ($node->attributes['end'] ?? $node->attributes['start'] ?? 0));
            $identity = (string) ($node->attributes['identity'] ?? (new Identity())->key($node));
            $frontiers[$identity] = new Frontier($reason, $source, (string) $node->literal, ['value'], [$identity], $node, $reason);
        }
        return array_values($frontiers);
    }
}
