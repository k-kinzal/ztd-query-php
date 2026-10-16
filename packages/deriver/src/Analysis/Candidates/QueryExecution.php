<?php

declare(strict_types=1);

namespace Deriver\Analysis\Candidates;

use Deriver\Analysis\Selection\QueryValidation;
use Deriver\Candidate\Cache;
use Deriver\Candidate\Context;
use Deriver\Candidate\Demand;
use Deriver\Candidate\Derivation;
use Deriver\Candidate\Index;
use Deriver\Model\Registration\Registry;
use Deriver\Project\Configuration;
use Deriver\Project\ProjectSnapshot;
use Deriver\Query\Query;
use Deriver\Result\Candidates\CandidateCollection;
use JsonException;

/**
 * Runs one query in the fixed order: validate the target, open a query context, place the demand, build the candidates.
 * @visibility parent
 */
final class QueryExecution
{
    /**
     * Captures the session-wide index, models and cache shared by every query.
     */
    public function __construct(public readonly Index $index, public readonly Configuration $configuration, public readonly Registry $models, public readonly ProjectSnapshot $snapshot, public readonly Cache $cache)
    {
    }

    /**
     * Derives only the selected observation and its dependencies.
     * @throws JsonException If result metadata cannot be encoded
     */
    public function derive(Query $query): CandidateCollection
    {
        $start = microtime(true);
        $symbol = (new QueryValidation($this->index->program, $this->snapshot->id))->owner($query);
        $context = new Context($this->index, $this->configuration, $this->models, $query->budget(), $this->cache);
        $context->cacheNamespace = hash('sha256', serialize([$query->scope(), $query->budget()->partitions, $query->budget()->recursion, $query->budget()->iterations]));
        $demand = new Demand(new Derivation($context));
        foreach ($query->scope()->entries as $entry) {
            $entryGraph = $this->index->graph($entry->symbol);
            if ($entryGraph !== null) {
                $context->entryFrames[strtolower($entry->symbol)] = $demand->frames($entryGraph, $query);
            }
        }
        return (new ResultBuilder())->build($query, $demand->select($symbol, $query), $context, $this->snapshot, $start);
    }
}
