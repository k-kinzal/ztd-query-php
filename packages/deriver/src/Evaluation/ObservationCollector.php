<?php

declare(strict_types=1);

namespace Deriver\Evaluation;

use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\CallableIdentity;
use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Control\ObservationLimit;
use Deriver\Evaluation\Model\StateStorage;
use Deriver\Memory\Location;
use Deriver\Memory\StorageCapture;
use Deriver\Query\ReturnQuery;
use Deriver\Query\StateQuery;
use Deriver\Query\TupleQuery;
use Deriver\Query\ValueQuery;
use Deriver\Reference\SourceRef;
use Deriver\Result\Alternative;
use Deriver\Result\Exceptional;
use Deriver\Value\Projection;
use Deriver\Value\Term;

/**
 * Observes evaluated registers and memory; it never re-evaluates query expressions.
 * @visibility root
 */
final class ObservationCollector
{
    /**
     * @param Context $context Query-local observations
     */
    public function __construct(public readonly Context $context)
    {
    }

    /**
     * Captures the requested side of an instruction.
     * @param CallableGraph $callable Executing callable
     * @param Instruction $instruction Executing instruction
     * @param State $state State at the observation
     * @param string $phase before, invocation, or after
     */
    public function instruction(CallableGraph $callable, Instruction $instruction, State $state, string $phase): void
    {
        $q = $this->context->query;
        $values = null;
        if ($q instanceof ValueQuery && $phase === 'after' && (new CallableIdentity())->key($q->expression->callable) === (new CallableIdentity())->key($callable->symbol) && $q->expression->register === $instruction->result) {
            $values = ['value' => $this->project($state->value($instruction->result), $q->projection->path, $state, $q->projection->slot, $instruction->source)];
        }
        if (($q instanceof StateQuery || $q instanceof TupleQuery) && (new CallableIdentity())->key($q->point->callable) === (new CallableIdentity())->key($callable->symbol) && $q->point->instruction === $instruction->id && $q->point->phase === $phase) {
            $values = [];
            if ($q instanceof StateQuery) {
                $values['state'] = $this->project($state->memory->read($state->local($q->variable)), $q->projection->path, $state, $q->projection->slot, $instruction->source);
            } else {
                foreach ($q->values as $name => $reference) {
                    $values[$name] = $state->memory->materialize($state->value($reference->register));
                }
            }
        }
        if ($values !== null) {
            $state->observed = true;
            $this->context->normal[] = new Alternative($values, $state->guard, $state->snapshot(), $state->evidence, (new StorageCapture())->capture($state->memory, $state->locals, $values));
            (new ObservationLimit($this->context))->enforce($instruction->source);
            if ($this->context->query->scope()->mode === 'symbolic' && !$this->repeated($callable, $state->block)) {
                $state->completion = new Completion('observed');
            }
        }
    }

    /**
     * Checks cycles, conservatively retaining loops with implicit exception-region edges.
     * @param CallableGraph $callable Observed graph
     * @param int $block Observation block
     * @return bool Whether the observation lies on a control-flow cycle
     */
    public function repeated(CallableGraph $callable, int $block): bool
    {
        if ($callable->regions !== []) {
            foreach ($callable->blocks as $candidate) {
                if ($candidate->loopHeader) {
                    return true;
                }
            }
        }
        $pending = $callable->blocks[$block]->terminator->targets ?? [];
        $seen = [];
        while ($pending !== []) {
            $next = array_pop($pending);
            if ($next === $block) {
                return true;
            }
            if (!isset($callable->blocks[$next]) || isset($seen[$next])) {
                continue;
            }
            $seen[$next] = true;
            array_push($pending, ...$callable->blocks[$next]->terminator->targets);
        }
        return false;
    }

    /**
     * Captures requested returns and exceptional completion paths.
     * @param CallableGraph $callable Completing callable
     * @param State $state Completed state
     */
    public function completion(CallableGraph $callable, State $state): void
    {
        if ($state->completion->kind === 'exit') {
            return;
        }
        $q = $this->context->query;
        if (!$q instanceof ReturnQuery) {
            if ($state->completion->kind === 'throw' && !$state->observed && $callable->symbol === $this->context->entrySymbol && array_sum($this->context->active) <= 1) {
                $this->context->exceptional[] = new Exceptional($state->memory->materialize($state->completion->value ?? new Term('throwable', 'Throwable')), $state->guard, $state->snapshot(), $state->evidence, (new StorageCapture())->capture($state->memory, $state->locals, ['exception' => $state->completion->value ?? new Term('throwable', 'Throwable')]));
                (new ObservationLimit($this->context))->enforce($callable->source);
            }
            return;
        }
        if ((new CallableIdentity())->key($q->symbol) !== (new CallableIdentity())->key($callable->symbol)) {
            return;
        }
        if ($q->scope()->mode === 'symbolic' && ($this->context->active[(new CallableIdentity())->key($callable->symbol)] ?? 1) > 1) {
            return;
        }
        $value = $state->memory->materialize($state->completion->value ?? Term::constant(null));
        if ($state->completion->kind === 'throw') {
            $this->context->exceptional[] = new Exceptional($value, $state->guard, $state->snapshot(), $state->evidence, (new StorageCapture())->capture($state->memory, $state->locals, ['exception' => $state->completion->value ?? new Term('throwable', 'Throwable')]));
        } else {
            $this->context->normal[] = new Alternative(['return' => $value], $state->guard, $state->snapshot(), $state->evidence, (new StorageCapture())->capture($state->memory, $state->locals, ['return' => $value]));
        }
        (new ObservationLimit($this->context))->enforce($callable->source);
    }

    /**
     * Selects a structure without losing unrelated fields.
     * @param Term $value Aggregate value
     * @param list<int|string> $path Projection keys
     * @param State $state Memory holding object and reference identity
     * @param string|null $slot Registered abstract receiver slot
     * @param SourceRef|null $source Observation provenance
     * @return Term Projected value
     */
    public function project(Term $value, array $path, State $state, ?string $slot = null, ?SourceRef $source = null): Term
    {
        if ($slot !== null) {
            $observed = (new StateStorage($this->context))->observe($value, $slot, $state, $source);
            $value = $value->secret ? new Term($observed->kind, $observed->literal, $observed->operands, $observed->attributes, true) : $observed;
        }
        if ($value->kind === 'domain' && is_string($value->literal) && $path !== []) {
            $domain = $this->context->models->extensions->domains[$value->literal] ?? null;
            return $domain === null ? new Term('projection', operands: [$value], attributes: ['type' => 'mixed']) : (new \Deriver\Model\Domain\DomainOperations())->apply($domain, 'project', $value, projection: new Projection($path));
        }
        $secret = false;
        foreach ($path as $key) {
            $value = $state->memory->dereference($value);
            $secret = $secret || $value->secret;
            if ($value->kind === 'object' && is_string($value->literal)) {
                $value = $state->memory->read(new Location('object:' . $value->literal, [$key]));
            } else {
                $value = $value->operands[$key] ?? new Term('array-read', operands: [$value, Term::constant($key)]);
            }
        }
        $value = $state->memory->materialize($value);
        return $secret && !$value->secret ? new Term($value->kind, $value->literal, $value->operands, $value->attributes, true) : $value;
    }
}
