<?php

declare(strict_types=1);

namespace Deriver\Deriver\Publication;

use Deriver\Deriver\Candidate;
use Deriver\Deriver\Candidate\Alternative;
use Deriver\Deriver\Candidate\CandidateCollection;
use Deriver\Deriver\Candidate\ResultRef;
use Deriver\Deriver\Candidate\Snapshot;
use Deriver\Deriver\Candidate\Statistics;
use Deriver\Deriver\Evaluator\Enumeration\Enumerator;
use Deriver\Deriver\Evaluator\Evidence\Contexts;
use Deriver\Deriver\Evaluator\Evidence\Forest;
use Deriver\Deriver\Evaluator\Scalar\Identity;
use Deriver\Deriver\Evaluator\Scope\Context;
use Deriver\Deriver\Evaluator\Target;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Identity\QueryEncoding;

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
     */
    public function collect(\Deriver\Deriver\Evaluator\Request\Query $query, Target $target, Term $graph, Context $context, Snapshot $snapshot, float $start): CandidateCollection
    {
        $grouped = [];
        $contexts = new Contexts();
        $enumerator = new Enumerator($graph);
        while (($row = $enumerator->next()) !== null) {
            [$tuple, $guard] = $row;
            if (count($grouped) === $query->budget()->maxCandidates - 1 && $enumerator->hasRemaining()) {
                $remainder = $enumerator->remainder($row);
                $tuple = $context->provenance->attach(new Term(\Deriver\Deriver\Evaluator\TermKind::UnexpandedChoice, operands: [$remainder], attributes: ['reason' => 'CANDIDATE_LIMIT']), (new Forest($context->provenance))->root($remainder));
            }
            $value = $tuple->kind === \Deriver\Deriver\Evaluator\TermKind::Tuple ? (count($tuple->operands) === 1 ? array_values($tuple->operands)[0] : Term::array($tuple->operands)) : $tuple;
            if ($context->provenance->get($tuple) !== null) {
                $value = $context->provenance->attach($value, $context->provenance->get($tuple));
            }
            if ($value->kind === \Deriver\Deriver\Evaluator\TermKind::Throwable) {
                $value = $context->provenance->attach(new Term(\Deriver\Deriver\Evaluator\TermKind::NoValue, $value->literal, [$value], ['type' => 'never', 'reason' => 'NO_VALUE_DEFINITION']), (new Forest($context->provenance))->root($value));
            }
            $root = (new ObservationRoot())->of($query, $target, $value, $context->index, $context->provenance);
            $evidence = new Alternative($root, $contexts->project($root), $snapshot);
            $key = (new Identity())->key($value);
            $grouped[$key] ??= [$value, []];
            $grouped[$key][1][] = $evidence;
            if ($tuple->kind === \Deriver\Deriver\Evaluator\TermKind::UnexpandedChoice) {
                break;
            }
        }
        $candidates = array_map(static fn (array $item): Candidate => new Candidate($item[0], $item[1]), array_values($grouped));
        $key = hash('sha256', $snapshot->id . ':candidates:' . (new QueryEncoding())->key($query));
        $statistics = new Statistics($context->constructedNodes, $context->bodyExpansions, microtime(true) - $start, memory_get_peak_usage(true), $context->referenceExpansions, $context->bodyExpansions, $context->evaluatorApplications, $context->sharedNodeHits, $context->constructedNodes, $context->bodies, $context->references);
        return new CandidateCollection($candidates, new ResultRef($key), $statistics, $context->stopReason !== null);
    }
}
