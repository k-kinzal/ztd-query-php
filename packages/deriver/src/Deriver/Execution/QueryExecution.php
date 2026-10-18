<?php

declare(strict_types=1);

namespace Deriver\Deriver\Execution;

use Deriver\Deriver\Candidate\ResultRef;
use Deriver\Deriver\Candidate\Snapshot;
use Deriver\Deriver\Candidate\Statistics;
use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\Execution\Call\ArgumentBinding;
use Deriver\Deriver\Evaluator\Execution\Call\PassedArgument;
use Deriver\Deriver\Evaluator\Execution\Call\Preparation\EntryProperties;
use Deriver\Deriver\Evaluator\Execution\Completion\Alternative;
use Deriver\Deriver\Evaluator\Execution\Context;
use Deriver\Deriver\Evaluator\Execution\Evaluator;
use Deriver\Deriver\Evaluator\Execution\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Execution\ObservationCollector;
use Deriver\Deriver\Evaluator\Execution\State;
use Deriver\Deriver\Evaluator\Request\EntryPoint;
use Deriver\Deriver\Evaluator\Request\Query;
use Deriver\Deriver\Evaluator\Request\ReturnQuery;
use Deriver\Deriver\Evaluator\Request\StateQuery;
use Deriver\Deriver\Evaluator\Request\ValueQuery;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Identity\QueryEncoding;
use Deriver\Deriver\Lowerer\Graph\Program;
use Deriver\Deriver\Selection\QueryValidation;
use Deriver\Deriver\Sources\SourceRef;
use Deriver\Exception\InvalidInputException;

/**
 * Normalizes public queries and runs only their declared entry contexts.
 * @visibility parent
 * @visibility Deriver\Deriver
 * @visibility Deriver\Deriver
 */
final class QueryExecution
{
    /**
     * @param Program $program \Deriver\Deriver\Candidate\Snapshot source graphs
     * @param EvaluatorConfig $configuration Explicit assumptions
     * @param Evaluators $evaluators Evaluator registry
     * @param Snapshot $snapshot Source/evaluator/world manifest
     */
    public function __construct(public readonly Program $program, public readonly EvaluatorConfig $configuration, public readonly Evaluators $evaluators, public readonly Snapshot $snapshot)
    {
    }

    /**
     * Executes one query and builds a complete assessment.
     * @param Query $query Requested observation
     * @return DerivationResult \Deriver\Deriver\Evaluator\Request\Query result
     */
    public function derive(Query $query): DerivationResult
    {
        $start = microtime(true);
        $context = new Context($this->program, $query, $this->configuration, $this->evaluators);
        $symbol = $this->owner($query);
        $entries = $query->scope()->mode === \Deriver\Deriver\Evaluator\Request\ScopeMode::Symbolic ? [new EntryPoint($symbol)] : $query->scope()->entries;
        foreach ($entries as $entry) {
            $this->entry($context, $entry, $query->scope()->mode === \Deriver\Deriver\Evaluator\Request\ScopeMode::Symbolic);
        }
        return $this->result($context, $symbol, $start);
    }

    /**
     * Builds one observation result, including bounded recovery after interruption.
     * @param Context $context Observation context
     * @param string $symbol Observation owner
     * @param float $start Execution start
     * @param string $executionIdentity Optional shared-execution identity
     * @return DerivationResult Derived observation
     */
    public function result(Context $context, string $symbol, float $start, string $executionIdentity = ''): DerivationResult
    {
        $query = $context->query;
        if ($context->normal === [] && array_filter($context->frontiers, static fn ($frontier): bool => !in_array($frontier->code, ['WIDENED', 'EXTERNAL_INPUT', 'PHP_WARNING'], true)) !== []) {
            $interrupted = in_array('BUDGET_EXCEEDED', array_column($context->frontiers, 'code'), true) ? 'BUDGET_EXCEEDED' : null;
            $candidate = $interrupted === null ? null : (new PartialObservation($this->program, $interrupted))->recover($query);
            $context->normal[] = $candidate ?? new Alternative(['residual' => Term::opaque('INCOMPLETE_DERIVATION')]);
        }
        $assessment = (new ResultAssessment())->assess($context);
        $assumptions = array_values(array_unique($context->assumptions));
        sort($assumptions);
        $id = hash('sha256', $this->snapshot->id . ':' . $symbol . ':' . (new QueryEncoding())->key($query) . $executionIdentity);
        $reached = $context->normal !== [] || ($query instanceof ReturnQuery && $context->exceptional !== []);
        return new DerivationResult(new ResultRef($id), $this->snapshot->id, $query, $context->normal, $context->exceptional, $reached ? \Deriver\Deriver\Evaluator\Execution\Completion\Reachability::MayReach : \Deriver\Deriver\Evaluator\Execution\Completion\Reachability::Unreachable, $assessment, array_values($context->frontiers), $assumptions, $context->evidence, new Statistics($context->transfers, count($context->graphs), sharedNodeHits: $context->summaries->hits, seconds: microtime(true) - $start, peakMemoryBytes: memory_get_peak_usage(true)), array_map(\Deriver\Deriver\Evaluator\Evidence\Frontier::fromDiagnostic(...), $this->program->diagnostics()));
    }

    /**
     * Observes one symbolic callable once under a shared exploration budget.
     * @param list<Query> $queries Observations in requested output order
     * @return ExecutionResultSet Independent observations from the shared execution
     * @throws InvalidInputException If owners, scopes, or budgets differ
     */
    public function together(array $queries): ExecutionResultSet
    {
        if ($queries === []) {
            return new ExecutionResultSet([]);
        }
        $symbol = $this->owner($queries[0]);
        $keys = [];
        foreach ($queries as $query) {
            if ($query->scope()->mode !== \Deriver\Deriver\Evaluator\Request\ScopeMode::Symbolic || (new \Deriver\Deriver\Lowerer\Declaration\CallableIdentity())->key($this->owner($query)) !== (new \Deriver\Deriver\Lowerer\Declaration\CallableIdentity())->key($symbol) || get_object_vars($query->budget()) !== get_object_vars($queries[0]->budget())) {
                throw new InvalidInputException('Shared execution requires symbolic queries for one callable with identical budgets.');
            }
            $keys[] = (new QueryEncoding())->key($query);
        }
        $identity = ':batch:' . hash('sha256', implode(':', $keys));
        $start = microtime(true);
        $context = new Context($this->program, $queries[0], $this->configuration, $this->evaluators);
        $batch = new \Deriver\Deriver\Evaluator\Execution\BatchObservations($queries, $context);
        $context->batch = $batch;
        $this->entry($context, new EntryPoint($symbol), true);
        $results = [];
        foreach ($queries as $index => $_) {
            $results[] = $this->result($batch->synchronize($index), $symbol, $start, $identity);
        }
        $context->batch = null;
        return new ExecutionResultSet($results);
    }

    /**
     * Executes a symbolic callable or an explicitly initialized entry.
     * @param Context $context \Deriver\Deriver\Evaluator\Request\Query context
     * @param EntryPoint $entry Entry contract
     * @param bool $symbolic Whether parameters represent all valid inputs
     * @throws InvalidInputException If supplied receiver properties cannot describe the entry object
     */
    public function entry(Context $context, EntryPoint $entry, bool $symbolic): void
    {
        $body = $this->program->callable($entry->symbol);
        if ($body === null) {
            $context->frontier('INCOMPLETE_SOURCE', new SourceRef($this->snapshot->id, '', 0, 0), $entry->symbol);
            return;
        }
        $machine = new Evaluator($context);
        $context->entrySymbol = $body->symbol;
        $arguments = [];
        foreach ($entry->arguments as $name => $value) {
            $arguments[] = new PassedArgument($value, is_string($name) ? $name : null);
        }
        $receiver = $body->static ? null : ($entry->receiver ?? ($body->className === '' ? null : Term::parameter('this', $body->className)));
        if (array_diff(array_keys($entry->captures), array_keys($body->captures)) !== []) {
            throw new InvalidInputException('Entry captures must name lexical captures declared by the closure.');
        }
        $captures = $entry->captures;
        foreach ($body->captures as $name => $byReference) {
            $captures[$name] ??= Term::parameter('capture:' . $name);
        }
        $initial = $this->initialState();
        if ($entry->properties !== []) {
            (new EntryProperties($context))->apply($body, $receiver, $entry->properties, $initial);
        }
        $states = (new ArgumentBinding($machine))->bind($body, $initial, $arguments, $receiver, $captures, symbolic: $symbolic || $entry->symbolicArguments);
        foreach ($states as $state) {
            if ($state->completion->kind === \Deriver\Deriver\Evaluator\Execution\Completion\CompletionKind::Normal) {
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
            if (str_starts_with($name, '$')) {
                $state->memory->cells['global:' . substr($name, 1)] = $value;
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
        if ($query->scope()->path !== null) {
            throw new InvalidInputException('Call paths require candidate derivation, not the execution backend.');
        }
        if (($query instanceof StateQuery || $query instanceof ValueQuery) && $query->projection->slot !== null && !isset($this->evaluators->state->slots[$query->projection->slot])) {
            throw new InvalidInputException('Projection requires a registered state slot: ' . $query->projection->slot);
        }
        return (new QueryValidation($this->program, $this->snapshot->id))->owner($query);
    }
}
