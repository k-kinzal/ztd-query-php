<?php

declare(strict_types=1);

namespace Deriver\Analysis\Candidates;

use Deriver\Candidate\Context;
use Deriver\Candidate\Enumeration\Enumerator;
use Deriver\Candidate\Evidence\Contexts;
use Deriver\Candidate\Evidence\Forest;
use Deriver\Candidate\Evidence\Provenance;
use Deriver\Candidate\Target;
use Deriver\Project\ProjectSnapshot;
use Deriver\Reference\ResultRef;
use Deriver\Result\Alternative;
use Deriver\Result\Candidate;
use Deriver\Result\CandidateCollection;
use Deriver\Result\Serialization\QueryEncoding;
use Deriver\Result\Statistics;
use Deriver\Value\Identity;
use Deriver\Value\Term;
use JsonException;

/**
 * Collects the candidate graph of one target into a candidate collection: enumerates the alternatives,
 * folds equal values into one candidate, keeps every derivation as its own evidence alternative, and
 * reports multiplicity, residual dependencies and stopped expansion separately.
 * @visibility parent
 */
final class CandidateCollector
{
    /**
     * Collects the enumerated alternatives of a candidate graph while retaining all residual dependencies.
     * @throws JsonException If result metadata cannot be encoded
     */
    public function collect(Target $target, Term $graph, Context $context, ProjectSnapshot $snapshot, float $start): CandidateCollection
    {
        $grouped = [];
        $contexts = new Contexts();
        $enumerator = new Enumerator($graph);
        while (($row = $enumerator->next()) !== null) {
            [$tuple, $guard] = $row;
            if (count($grouped) === $target->query->budget()->maxCandidates - 1 && $enumerator->hasRemaining()) {
                $remainder = $enumerator->remainder($row);
                $tuple = new Term('unexpanded-choice', operands: [$remainder], attributes: ['reason' => 'CANDIDATE_LIMIT'], evidence: (new Forest())->root($remainder));
            }
            $value = $tuple->kind === 'tuple' ? (count($tuple->operands) === 1 ? array_values($tuple->operands)[0] : Term::array($tuple->operands)) : $tuple;
            if ($tuple->evidence !== null) {
                $value = Provenance::attach($value, $tuple->evidence);
            }
            if ($value->kind === 'throwable') {
                $value = new Term('no-value', $value->literal, [$value], ['type' => 'never', 'reason' => 'NO_VALUE_DEFINITION'], evidence: $value->evidence);
            }
            $root = (new ObservationRoot())->of($target, $value, $context->index);
            $evidence = new Alternative($root, $contexts->project($root), $snapshot);
            $key = (new Identity())->key($value);
            $grouped[$key] ??= [$value, []];
            $grouped[$key][1][] = $evidence;
            if ($tuple->kind === 'unexpanded-choice') {
                break;
            }
        }
        $candidates = array_map(static fn (array $item): Candidate => new Candidate($item[0], $item[1]), array_values($grouped));
        $key = hash('sha256', $snapshot->id . ':candidates:' . (new QueryEncoding())->key($target->query));
        $statistics = new Statistics($context->constructedNodes, $context->bodyExpansions, $context->sharedNodeHits, microtime(true) - $start, memory_get_peak_usage(true), $context->referenceExpansions, $context->bodyExpansions, $context->modelApplications, $context->sharedNodeHits, $context->constructedNodes, $context->cache->count(), $context->bodies, $context->references);
        return new CandidateCollection($candidates, new ResultRef($key), $statistics, $context->stopReason !== null);
    }
}
