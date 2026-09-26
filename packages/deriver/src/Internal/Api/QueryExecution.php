<?php

declare(strict_types=1);

namespace Deriver\Internal\Api;

use Deriver\Api\InvalidInputException;
use Deriver\Api\Project\Configuration;
use Deriver\Api\Project\EntryPoint;
use Deriver\Api\Project\ProjectSnapshot;
use Deriver\Api\Query\Query;
use Deriver\Api\Reference\ResultRef;
use Deriver\Api\Reference\SourceRef;
use Deriver\Api\Result\Alternative;
use Deriver\Api\Result\DerivationResult;
use Deriver\Api\Result\Statistics;
use Deriver\Internal\IR\Program;
use Deriver\Internal\Model\Registry;
use Deriver\Internal\Solver\Call\ArgumentBinding;
use Deriver\Internal\Solver\Call\PassedArgument;
use Deriver\Internal\Solver\Context;
use Deriver\Internal\Solver\Machine;
use Deriver\Internal\Solver\ObservationCollector;
use Deriver\Internal\Solver\State;
use Deriver\Report\QueryEncoding;
use Deriver\Value\Term;
use JsonException;

/**
 * Normalizes public queries and runs only their declared entry contexts.
 * @visibility root
 */
final class QueryExecution
{
    /**
     * @param Program $program Snapshot source graphs
     * @param Configuration $configuration Explicit assumptions
     * @param Registry $models Model registry
     * @param ProjectSnapshot $snapshot Source/model/world manifest
     */
    public function __construct(public readonly Program $program, public readonly Configuration $configuration, public readonly Registry $models, public readonly ProjectSnapshot $snapshot)
    {
    }

    /**
     * Executes one query and builds a complete assessment.
     * @param Query $query Requested observation
     * @return DerivationResult Query result
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function derive(Query $query): DerivationResult
    {
        $start = microtime(true);
        $context = new Context($this->program, $query, $this->configuration, $this->models);
        $symbol = $this->owner($query);
        $entries = $query->scope()->mode === 'symbolic' ? [new EntryPoint($symbol)] : $query->scope()->entries;
        foreach ($entries as $entry) {
            $this->entry($context, $entry, $query->scope()->mode === 'symbolic');
        }
        if ($context->normal === [] && array_filter($context->frontiers, static fn ($frontier): bool => !in_array($frontier->code, ['WIDENED', 'EXTERNAL_INPUT', 'PHP_WARNING'], true)) !== []) {
            $context->normal[] = new Alternative(['residual' => Term::opaque('INCOMPLETE_DERIVATION')]);
        }
        $assessment = (new ResultAssessment())->assess($context);
        $assumptions = array_values(array_unique($context->assumptions));
        sort($assumptions);
        $interruption = $context->stopReason === null ? '' : ':' . $context->stopReason . ':' . $context->transfers;
        $id = hash('sha256', $this->snapshot->id . ':' . $symbol . ':' . (new QueryEncoding())->key($query) . $interruption);
        $reached = $context->normal !== [] || ($query instanceof \Deriver\Api\Query\ReturnQuery && $context->exceptional !== []);
        return new DerivationResult(new ResultRef($id), $this->snapshot->id, $query, $context->normal, $context->exceptional, $reached ? 'may-reach' : 'unreachable', $assessment, array_values($context->frontiers), $assumptions, $context->evidence, new Statistics($context->transfers, count($context->graphs), cacheHits: $context->summaries->hits, seconds: microtime(true) - $start, peakMemoryBytes: memory_get_peak_usage(true)), $this->program->diagnostics());
    }

    /**
     * Executes a symbolic callable or an explicitly initialized entry.
     * @param Context $context Query context
     * @param EntryPoint $entry Entry contract
     * @param bool $symbolic Whether parameters represent all valid inputs
     */
    public function entry(Context $context, EntryPoint $entry, bool $symbolic): void
    {
        $body = $this->program->callable($entry->symbol);
        if ($body === null) {
            $context->frontier('INCOMPLETE_SOURCE', new SourceRef($this->snapshot->id, '', 0, 0), $entry->symbol);
            return;
        }
        $machine = new Machine($context);
        $context->entrySymbol = $body->symbol;
        $arguments = [];
        foreach ($entry->arguments as $name => $value) {
            $arguments[] = new PassedArgument($value, is_string($name) ? $name : null);
        }
        $receiver = $body->static ? null : ($entry->receiver ?? ($body->className === '' ? null : Term::parameter('this', $body->className)));
        $states = (new ArgumentBinding($machine))->bind($body, new State(), $arguments, $receiver, symbolic: $symbolic);
        foreach ($states as $state) {
            if ($state->completion->kind === 'normal') {
                $machine->run($body, $state);
            } else {
                (new ObservationCollector($context))->completion($body, $state);
            }
        }
    }

    /**
     * Validates source ownership before executing a query.
     * @param Query $query Requested observation
     * @return string Owning callable identity
     * @throws InvalidInputException If a reference belongs to another snapshot
     */
    public function owner(Query $query): string
    {
        if (($query instanceof \Deriver\Api\Query\StateQuery || $query instanceof \Deriver\Api\Query\ValueQuery) && $query->projection->slot !== null && !isset($this->models->state->slots[$query->projection->slot])) {
            throw new InvalidInputException('Projection requires a registered state slot: ' . $query->projection->slot);
        }
        return (new QueryValidation($this->program, $this->snapshot->id))->owner($query);
    }
}
