<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator;

use Closure;
use Deriver\Deriver\Evaluator\Index\BodyIndex;

/**
 * Places a selected target's demand on the engine: the selected expression, return, tuple, or storage definition in each entry frame.
 * Nothing outside the engine selects definitions or reads storage directly; the result is one candidate graph.
 * @visibility namespace
 * @visibility Deriver\Deriver\Publication
 */
final class Demand
{
    /**
     * Captures the engine the demand is placed on.
     */
    public function __construct(public readonly Evaluator $engine)
    {
    }

    /**
     * Expands the selected definition of the owning symbol in every frame the query scope supplies.
     * A symbol without captured source stays an unexpanded reference.
     */
    public function select(Target $target): Term
    {
        $graph = $this->engine->context->index->graph($target->owner);
        if ($graph === null) {
            return (new Enumeration\Choices($this->engine->context->provenance))->make([[new Term(TermKind::Reference, $target->owner, attributes: ['reason' => 'MISSING_SOURCE', 'scope' => $target->owner]), []]]);
        }
        if ($this->engine->context->callPath !== null) {
            return (new Invocation\Path\Traversal($this->engine, $this->engine->context->callPath))->select($graph, fn (Scope\Frame $frame): Term => $this->pathObservation($frame, $target));
        }
        $branches = array_map(fn (Scope\Frame $frame): Closure => fn (): Term => $this->observe($frame, $target), $this->frames($graph, $this->engine->context->entries));
        $rows = array_map(static fn (Term $value): array => [$value, []], $this->engine->context->search->all($branches));
        return (new Enumeration\Choices($this->engine->context->provenance))->make($rows);
    }

    /**
     * Preserves call-site branch semantics when the selector observes an actual argument.
     */
    public function pathObservation(Scope\Frame $frame, Target $target): Term
    {
        if ($this->engine->context->callPath?->atCall === true && $target->register !== null) {
            return (new Invocation\Path\Edge($this->engine))->observe($frame, $frame->graph->positions[$target->register][0], $this->engine->context->budget->maxDepth, fn (): Term => $this->observe($frame, $target));
        }
        return $this->observe($frame, $target);
    }

    /**
     * Builds the entry frames the query scope declares for a graph, or one source frame without explicit bindings.
     * @param list<Request\EntryPoint> $entries Invocation inputs
     * @return list<Scope\Frame>
     */
    public function frames(BodyIndex $graph, array $entries): array
    {
        $frames = [];
        foreach ($entries as $entry) {
            if (strcasecmp($entry->symbol, $graph->body->symbol) !== 0) {
                continue;
            }
            $bindings = $entry->captures;
            foreach ($entry->arguments as $name => $value) {
                $parameter = is_string($name) ? $name : ($graph->body->parameters[$name]->name ?? (string) $name);
                $bindings[$parameter] = $value;
            }
            if ($entry->receiver !== null) {
                $bindings['this'] = $entry->receiver;
            }
            $identity = 'entry:' . hash('sha256', serialize([$entry->symbol, $bindings, $entry->properties]));
            $frames[] = new Scope\Frame($graph, $identity, $bindings, $entry->properties, invocation: !$entry->symbolicArguments);
        }
        return $frames === [] ? [new Scope\Frame($graph, 'source:' . $graph->body->symbol)] : $frames;
    }

    /**
     * Selects the requested expression, return, tuple, or storage definition.
     */
    public function observe(Scope\Frame $frame, Target $target): Term
    {
        $depth = $this->engine->context->budget->maxDepth;
        if ($target->kind === TargetKind::Parameter) {
            $parameter = array_values(array_filter($frame->graph->body->parameters, static fn ($parameter): bool => $parameter->name === $target->parameter))[0];
            $local = new Call\BoundInstruction('parameter:' . $target->parameter, 'local', $parameter->source ?? $frame->graph->body->source, name: (string) $target->parameter);
            return $this->tuple(['value' => (new Expression\Origins($this->engine))->parameter($frame, $local, $depth)]);
        }
        if ($target->kind === TargetKind::Return) {
            return $this->returns($frame, $depth);
        }
        if ($target->kind === TargetKind::Value && $target->register !== null) {
            $value = $this->engine->value($frame, $target->register, $depth);
            [$block, $offset] = $frame->graph->positions[$target->register];
            return $this->tuple(['value' => $this->project($frame, $value, $target, $block, $offset, $depth)]);
        }
        if ($target->kind === TargetKind::Tuple) {
            $branches = array_map(fn (string $register): Closure => fn (): Term => $this->engine->value($frame, $register, $depth), array_values($target->registers));
            $values = array_combine(array_keys($target->registers), $this->engine->context->search->all($branches));
            return $this->tuple($values);
        }
        if ($target->kind === TargetKind::State && $target->point !== null) {
            $register = $frame->graph->instructions[$target->point->instruction];
            [$block, $offset] = $frame->graph->positions[$register];
            $offset += $target->point->phase === \Deriver\Deriver\Lowerer\Graph\ObservationPhase::After ? 1 : 0;
            foreach ($frame->graph->definitions as $definition) {
                if ($definition->operation === 'local' && $definition->name === $target->variable) {
                    $value = (new Expression\Storage($this->engine))->search($frame, $definition->result, $block, $offset, $depth);
                    return $this->tuple(['state' => $this->project($frame, $value, $target, $block, $offset, $depth)]);
                }
            }
        }
        return $this->tuple(['value' => new Term(TermKind::Reference, 'observation', attributes: ['reason' => 'UNRESOLVED_OBSERVATION'])]);
    }

    /**
     * Applies the target's slot and path projection to a selected value at its observation position.
     */
    public function project(Scope\Frame $frame, Term $value, Target $target, int $block, int $offset, int $depth): Term
    {
        if ($target->projection === null) {
            return $value;
        }
        if ($target->projection->slot !== null) {
            $value = (new Memory\Slots($this->engine))->before($frame, $value, $target->projection->slot, $block, $offset, $depth);
        }
        foreach ($target->projection->path as $key) {
            $value = $this->engine->operation('array-read', '', [$value, Term::constant($key)]);
        }
        return $value;
    }

    /**
     * Combines conditional declarations using the same return expansion.
     */
    public function returns(Scope\Frame $frame, int $depth): Term
    {
        return $this->tuple(['return' => (new Micro\ReturnEvaluator())->select($this->engine, $frame, $depth)]);
    }

    /**
     * Keeps the selected names on one tuple while preserving the common choices of its members.
     * @param array<string, Term> $values
     */
    public function tuple(array $values): Term
    {
        $names = array_keys($values);
        $result = (new Enumeration\Choices($this->engine->context->provenance))->apply('tuple', array_values($values), static fn (array $items): Term => new Term(TermKind::Tuple, operands: array_combine($names, $items)), $this->engine->context->budget->partitions);
        return $result->kind === TermKind::Operation ? new Term(TermKind::Tuple, operands: $values, attributes: ['reason' => 'ENUMERATION_LIMIT']) : $result;
    }
}
