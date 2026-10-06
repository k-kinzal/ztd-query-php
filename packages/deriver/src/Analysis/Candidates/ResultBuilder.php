<?php

declare(strict_types=1);

namespace Deriver\Analysis\Candidates;

use Deriver\Evaluation\Candidate\Context;
use Deriver\Project\ProjectSnapshot;
use Deriver\Query\Query;
use Deriver\Reference\ResultRef;
use Deriver\Result\Serialization\QueryEncoding;
use Deriver\Result\Statistics;
use Deriver\Value\Identity;
use Deriver\Value\Term;
use JsonException;

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
    public function build(Query $query, Term $graph, Context $context, ProjectSnapshot $snapshot, float $start): \Deriver\Result\Candidates\CandidateCollection
    {
        $grouped = [];
        $contexts = new \Deriver\Evaluation\Candidate\Evidence\Contexts();
        $cursor = new \Deriver\Evaluation\Candidate\Enumeration\Cursor($graph);
        while (($row = $cursor->next()) !== null) {
            [$tuple, $guard] = $row;
            if (count($grouped) === $query->budget()->maxCandidates - 1 && $cursor->hasRemaining()) {
                $remainder = $cursor->remainder($row);
                $tuple = new Term('unexpanded-choice', operands: [$remainder], attributes: ['reason' => 'CANDIDATE_LIMIT'], evidence: (new \Deriver\Evaluation\Candidate\Evidence\Forest())->root($remainder));
            }
            $value = $tuple->kind === 'tuple' ? (count($tuple->operands) === 1 ? array_values($tuple->operands)[0] : Term::array($tuple->operands)) : $tuple;
            if ($tuple->evidence !== null) {
                $value = \Deriver\Evaluation\Candidate\Evidence\Provenance::attach($value, $tuple->evidence);
            }
            if ($value->kind === 'throwable') {
                $value = new Term('no-value', $value->literal, [$value], ['type' => 'never', 'reason' => 'NO_VALUE_DEFINITION'], evidence: $value->evidence);
            }
            $root = $this->observation($query, $value, $context, $snapshot);
            $evidence = new \Deriver\Result\Evidence\Alternative($root, $contexts->project($root), $snapshot);
            $key = (new Identity())->key($value);
            $grouped[$key] ??= [$value, []];
            $grouped[$key][1][] = $evidence;
            if ($tuple->kind === 'unexpanded-choice') {
                break;
            }
        }
        $candidates = array_map(static fn (array $item): \Deriver\Result\Candidates\Candidate => new \Deriver\Result\Candidates\Candidate($item[0], $item[1]), array_values($grouped));
        $key = hash('sha256', $snapshot->id . ':candidates:' . (new QueryEncoding())->key($query));
        $statistics = new Statistics($context->constructedNodes, $context->bodyExpansions, $context->sharedNodeHits, microtime(true) - $start, memory_get_peak_usage(true), $context->referenceExpansions, $context->bodyExpansions, $context->modelApplications, $context->sharedNodeHits, $context->constructedNodes, $context->cache->count(), $context->bodies, $context->references);
        return new \Deriver\Result\Candidates\CandidateCollection($candidates, new ResultRef($key), $statistics, $context->stopReason !== null);
    }

    /**
     * Identifies the public target independently of the value reached by expansion.
     * @throws JsonException If query metadata cannot be encoded
     */
    public function observation(Query $query, Term $value, Context $context, ProjectSnapshot $snapshot): \Deriver\Result\Evidence\Node
    {
        $owner = (new \Deriver\Analysis\QueryValidation($context->index->program, $snapshot->id))->owner($query);
        $source = match (true) {
            $query instanceof \Deriver\Query\ValueQuery => $query->expression->source,
            $query instanceof \Deriver\Query\StateQuery, $query instanceof \Deriver\Query\TupleQuery => $query->point->source,
            default => $context->index->graph($owner)?->body->source,
        };
        $target = match (true) {
            $query instanceof \Deriver\Query\ValueQuery => $query->expression->register,
            $query instanceof \Deriver\Query\ParameterQuery => $query->parameter,
            $query instanceof \Deriver\Query\StateQuery => $query->variable,
            default => $owner,
        };
        return new \Deriver\Result\Evidence\Node('observation', ['value' => (new \Deriver\Evaluation\Candidate\Evidence\Forest())->root($value)], $source, ['query' => (new QueryEncoding())->key($query), 'owner' => $owner, 'target' => $target, 'role' => $query::class]);
    }

}
