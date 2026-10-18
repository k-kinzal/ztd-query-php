<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution;

use Deriver\Deriver\Evaluator\Execution\Completion\Alternative;
use Deriver\Deriver\Evaluator\Execution\Completion\Exceptional;
use Deriver\Deriver\Evaluator\Execution\Control\ObservationLimit;
use Deriver\Deriver\Evaluator\Execution\Memory\Location;
use Deriver\Deriver\Evaluator\Execution\Memory\Slots\StateStorage;
use Deriver\Deriver\Evaluator\Execution\Memory\StorageCapture;
use Deriver\Deriver\Evaluator\Request\ReturnQuery;
use Deriver\Deriver\Evaluator\Request\StateQuery;
use Deriver\Deriver\Evaluator\Request\TupleQuery;
use Deriver\Deriver\Evaluator\Request\ValueQuery;
use Deriver\Deriver\Lowerer\Declaration\CallableIdentity;
use Deriver\Deriver\Lowerer\Graph\CallableGraph;
use Deriver\Deriver\Lowerer\Graph\Instruction;
use Deriver\Deriver\Sources\SourceRef;

/**
 * Observes evaluated registers and memory; it never re-evaluates query expressions.
 * @visibility namespace
 * @visibility Deriver\Deriver\Execution
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
     * @param State $state \Deriver\Deriver\Evaluator\Execution\State at the observation
     * @param \Deriver\Deriver\Lowerer\Graph\ObservationPhase $phase before, invocation, or after
     * @param bool $stop Whether a completed non-repeating symbolic observation may stop its path
     */
    public function instruction(CallableGraph $callable, Instruction $instruction, State $state, \Deriver\Deriver\Lowerer\Graph\ObservationPhase $phase, bool $stop = true): void
    {
        if ($this->context->batch !== null) {
            $this->context->batch->instruction($callable, $instruction, $state, $phase);
            return;
        }
        $q = $this->context->query;
        $values = null;
        if ($q instanceof ValueQuery && $phase === \Deriver\Deriver\Lowerer\Graph\ObservationPhase::After && (new CallableIdentity())->key($q->expression->callable) === (new CallableIdentity())->key($callable->symbol) && $q->expression->register === $instruction->result) {
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
            if ($stop && $this->context->query->scope()->mode === \Deriver\Deriver\Evaluator\Request\ScopeMode::Symbolic && !$this->repeated($callable, $state->block)) {
                $state->completion = new Completion\Completion(Completion\CompletionKind::Observed);
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
        if ($this->context->batch !== null) {
            $this->context->batch->completion($callable, $state);
            return;
        }
        if ($state->completion->kind === Completion\CompletionKind::Exit) {
            return;
        }
        $q = $this->context->query;
        if (!$q instanceof ReturnQuery) {
            if ($state->completion->kind === Completion\CompletionKind::Throw && !$state->observed && $callable->symbol === $this->context->entrySymbol && array_sum($this->context->active) <= 1) {
                $this->context->exceptional[] = new Exceptional($state->memory->materialize($state->completion->value ?? new \Deriver\Deriver\Evaluator\Term(\Deriver\Deriver\Evaluator\TermKind::Throwable, 'Throwable')), $state->guard, $state->snapshot(), $state->evidence, (new StorageCapture())->capture($state->memory, $state->locals, ['exception' => $state->completion->value ?? new \Deriver\Deriver\Evaluator\Term(\Deriver\Deriver\Evaluator\TermKind::Throwable, 'Throwable')]));
                (new ObservationLimit($this->context))->enforce($callable->source);
            }
            return;
        }
        if ((new CallableIdentity())->key($q->symbol) !== (new CallableIdentity())->key($callable->symbol)) {
            return;
        }
        if ($q->scope()->mode === \Deriver\Deriver\Evaluator\Request\ScopeMode::Symbolic && ($this->context->active[(new CallableIdentity())->key($callable->symbol)] ?? 1) > 1) {
            return;
        }
        $value = $state->memory->materialize($state->completion->value ?? \Deriver\Deriver\Evaluator\Term::constant(null));
        if ($state->completion->kind === Completion\CompletionKind::Throw) {
            $this->context->exceptional[] = new Exceptional($value, $state->guard, $state->snapshot(), $state->evidence, (new StorageCapture())->capture($state->memory, $state->locals, ['exception' => $state->completion->value ?? new \Deriver\Deriver\Evaluator\Term(\Deriver\Deriver\Evaluator\TermKind::Throwable, 'Throwable')]));
        } else {
            $this->context->normal[] = new Alternative(['return' => $value], $state->guard, $state->snapshot(), $state->evidence, (new StorageCapture())->capture($state->memory, $state->locals, ['return' => $value]));
        }
        (new ObservationLimit($this->context))->enforce($callable->source);
    }

    /**
     * Selects a structure without losing unrelated fields.
     * @param \Deriver\Deriver\Evaluator\Term $value Aggregate value
     * @param list<int|string> $path \Deriver\Deriver\Evaluator\Projection keys
     * @param State $state Memory holding object and reference identity
     * @param string|null $slot Registered abstract receiver slot
     * @param SourceRef|null $source Observation provenance
     * @return \Deriver\Deriver\Evaluator\Term Projected value
     */
    public function project(\Deriver\Deriver\Evaluator\Term $value, array $path, State $state, ?string $slot = null, ?SourceRef $source = null): \Deriver\Deriver\Evaluator\Term
    {
        if ($slot !== null) {
            $value = (new StateStorage($this->context))->observe($value, $slot, $state, $source);
        }
        if ($value->kind === \Deriver\Deriver\Evaluator\TermKind::Domain && is_string($value->literal) && $path !== []) {
            $domain = $this->context->evaluators->extensions->domains[$value->literal] ?? null;
            return $domain === null ? new \Deriver\Deriver\Evaluator\Term(\Deriver\Deriver\Evaluator\TermKind::Projection, operands: [$value], attributes: ['type' => 'mixed']) : (new \Deriver\Deriver\Evaluator\Domain\DomainOperations())->apply($domain, 'project', $value, projection: new \Deriver\Deriver\Evaluator\Projection($path));
        }
        foreach ($path as $key) {
            $value = $state->memory->dereference($value);
            if ($value->kind === \Deriver\Deriver\Evaluator\TermKind::Object && is_string($value->literal)) {
                $value = $state->memory->read(new Location('object:' . $value->literal, [$key]));
            } else {
                $value = $value->operands[$key] ?? new \Deriver\Deriver\Evaluator\Term(\Deriver\Deriver\Evaluator\TermKind::ArrayRead, operands: [$value, \Deriver\Deriver\Evaluator\Term::constant($key)]);
            }
        }
        return $state->memory->materialize($value);
    }
}
