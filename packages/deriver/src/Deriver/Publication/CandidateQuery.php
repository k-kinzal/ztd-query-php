<?php

declare(strict_types=1);

namespace Deriver\Deriver\Publication;

use Deriver\Deriver\Candidate\CandidateCollection;
use Deriver\Deriver\Candidate\Snapshot;
use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\Demand;
use Deriver\Deriver\Evaluator\Evaluator;
use Deriver\Deriver\Evaluator\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Index\DefinitionIndex;
use Deriver\Deriver\Evaluator\Request\Query;
use Deriver\Deriver\Evaluator\Scope\Context;
use Deriver\Deriver\Selection\TargetResolution;

/**
 * Runs one query through the fixed stage order: select the target, open a query context, place the demand,
 * collect the candidates. Nothing here selects definitions, reads storage or constructs a candidate itself.
 * @visibility parent
 * @visibility Deriver
 */
final class CandidateQuery
{
    /**
     * Receives the captured declarations and settings for one evaluation.
     */
    public function __construct(private readonly DefinitionIndex $index, private readonly EvaluatorConfig $configuration, private readonly Evaluators $evaluators, private readonly Snapshot $snapshot)
    {
    }

    /**
     * Derives only the selected observation and its dependencies.
     */
    public function derive(Query $query): CandidateCollection
    {
        $start = microtime(true);
        $target = (new TargetResolution($this->index->program, $this->snapshot->id))->resolve($query);
        $context = new Context($this->index, $this->configuration, $this->evaluators, $query->budget());
        $context->entries = $query->scope()->entries;
        $context->callPath = $query->scope()->path;
        return (new CandidateCollector())->collect($query, $target, (new Evaluator($context))->evaluate($target), $context, $this->snapshot, $start);
    }
}
