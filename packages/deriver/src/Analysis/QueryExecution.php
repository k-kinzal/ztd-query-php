<?php

declare(strict_types=1);

namespace Deriver\Analysis;

use Deriver\ControlFlow\Program;
use Deriver\Evaluation\Call\ArgumentBinding;
use Deriver\Evaluation\Call\PassedArgument;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\ObservationCollector;
use Deriver\Evaluation\State;
use Deriver\Exception\InvalidInputException;
use Deriver\Model\Registration\Registry;
use Deriver\Project\Configuration;
use Deriver\Project\EntryPoint;
use Deriver\Project\ProjectSnapshot;
use Deriver\Query\Query;
use Deriver\Query\ReturnQuery;
use Deriver\Query\StateQuery;
use Deriver\Query\ValueQuery;
use Deriver\Reference\ResultRef;
use Deriver\Reference\SourceRef;
use Deriver\Result\Alternative;
use Deriver\Result\DerivationResult;
use Deriver\Result\Serialization\QueryEncoding;
use Deriver\Result\Statistics;
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
        $reached = $context->normal !== [] || ($query instanceof ReturnQuery && $context->exceptional !== []);
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
        $captures = [];
        foreach ($symbolic ? $body->captures : [] as $name => $byReference) {
            $captures[$name] = Term::parameter('capture:' . $name);
        }
        $states = (new ArgumentBinding($machine))->bind($body, $this->initialState(), $arguments, $receiver, $captures, symbolic: $symbolic);
        foreach ($states as $state) {
            if ($state->completion->kind === 'normal') {
                $machine->run($body, $state);
            } else {
                (new ObservationCollector($context))->completion($body, $state);
            }
        }
    }

    /**
     * Materializes explicit globals before any source operation can mutate shared storage.
     * @return State Captured entry environment
     */
    public function initialState(): State
    {
        $state = new State();
        foreach ($this->configuration->environment as $name => $value) {
            if (str_starts_with($name, 'global:')) {
                $state->memory->cells[$name] = $value;
            }
        }
        return $state;
    }

    /**
     * Validates source ownership before executing a query.
     * @param Query $query Requested observation
     * @return string Owning callable identity
     * @throws InvalidInputException If a reference belongs to another snapshot
     */
    public function owner(Query $query): string
    {
        if (($query instanceof StateQuery || $query instanceof ValueQuery) && $query->projection->slot !== null && !isset($this->models->state->slots[$query->projection->slot])) {
            throw new InvalidInputException('Projection requires a registered state slot: ' . $query->projection->slot);
        }
        return (new QueryValidation($this->program, $this->snapshot->id))->owner($query);
    }
}
