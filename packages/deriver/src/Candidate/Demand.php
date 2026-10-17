<?php

declare(strict_types=1);

namespace Deriver\Candidate;

use Deriver\Candidate\Index\BodyIndex;
use Deriver\ControlFlow\Instruction;
use Deriver\Value\Term;

/**
 * Places a selected target's demand on the engine: the selected expression, return, tuple, or storage definition in each entry frame.
 * Nothing outside the engine selects definitions or reads storage directly; the result is one candidate graph.
 * @visibility namespace
 * @visibility Deriver\Analysis\Candidates
 */
final class Demand
{
    /**
     * Captures the engine the demand is placed on.
     */
    public function __construct(public readonly Derivation $engine)
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
            return (new Choices())->make([[new Term('reference', $target->owner, attributes: ['reason' => 'MISSING_SOURCE', 'scope' => $target->owner]), []]]);
        }
        $rows = [];
        foreach ($this->frames($graph, $target) as $frame) {
            $rows[] = [$this->observe($frame, $target), []];
        }
        return (new Choices())->make($rows);
    }

    /**
     * Builds the entry frames the query scope declares for a graph, or one source frame without explicit bindings.
     * @return list<Frame>
     */
    public function frames(BodyIndex $graph, Target $target): array
    {
        $frames = [];
        foreach ($target->query->scope()->entries as $entry) {
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
            $frames[] = new Frame($graph, $identity, $bindings, $entry->properties, invocation: !$entry->symbolicArguments);
        }
        return $frames === [] ? [new Frame($graph, 'source:' . $graph->body->symbol)] : $frames;
    }

    /**
     * Selects the requested expression, return, tuple, or storage definition.
     */
    public function observe(Frame $frame, Target $target): Term
    {
        $depth = $target->query->budget()->maxDepth;
        if ($target->kind === 'parameter') {
            $parameter = array_values(array_filter($frame->graph->body->parameters, static fn ($parameter): bool => $parameter->name === $target->parameter))[0];
            $local = new Instruction('parameter:' . $target->parameter, 'local', $parameter->source ?? $frame->graph->body->source, name: (string) $target->parameter);
            return $this->tuple(['value' => (new Origins($this->engine))->parameter($frame, $local, $depth)]);
        }
        if ($target->kind === 'return') {
            return $this->returns($frame, $depth);
        }
        if ($target->kind === 'value' && $target->register !== null) {
            $value = $this->engine->value($frame, $target->register, $depth);
            [$block, $offset] = $frame->graph->positions[$target->register];
            return $this->tuple(['value' => $this->project($frame, $value, $target, $block, $offset, $depth)]);
        }
        if ($target->kind === 'tuple') {
            $values = [];
            foreach ($target->registers as $name => $register) {
                $values[$name] = $this->engine->value($frame, $register, $depth);
            }
            return $this->tuple($values);
        }
        if ($target->kind === 'state' && $target->point !== null) {
            $register = $frame->graph->instructions[$target->point->instruction];
            [$block, $offset] = $frame->graph->positions[$register];
            $offset += $target->point->phase === 'after' ? 1 : 0;
            foreach ($frame->graph->definitions as $definition) {
                if ($definition->operation === 'local' && $definition->name === $target->variable) {
                    $value = (new Storage($this->engine))->search($frame, $definition->result, $block, $offset, $depth);
                    return $this->tuple(['state' => $this->project($frame, $value, $target, $block, $offset, $depth)]);
                }
            }
        }
        return $this->tuple(['value' => new Term('reference', 'observation', attributes: ['reason' => 'UNRESOLVED_OBSERVATION'])]);
    }

    /**
     * Applies the target's slot and path projection to a selected value at its observation position.
     */
    public function project(Frame $frame, Term $value, Target $target, int $block, int $offset, int $depth): Term
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
    public function returns(Frame $frame, int $depth): Term
    {
        $values = [[$this->engine->returns($frame, $depth), []]];
        foreach ($this->engine->context->index->program->variants($frame->graph->body->symbol) as $variant) {
            $graph = $this->engine->context->index->graph($variant);
            if ($graph !== null) {
                $values[] = [$this->engine->returns(new Frame($graph, 'source:' . $variant, $frame->bindings, $frame->properties, $frame->calls, $frame->invocation, calledClass: $frame->calledClass), $depth), []];
            }
        }
        return $this->tuple(['return' => (new Choices())->make($values)]);
    }

    /**
     * Keeps the selected names on one tuple while preserving the common choices of its members.
     * @param array<string, Term> $values
     */
    public function tuple(array $values): Term
    {
        $names = array_keys($values);
        $result = (new Choices())->apply('tuple', array_values($values), static fn (array $items): Term => new Term('tuple', operands: array_combine($names, $items)), $this->engine->context->budget->partitions);
        return $result->kind === 'operation' ? new Term('tuple', operands: $values, attributes: ['reason' => 'ENUMERATION_LIMIT']) : $result;
    }
}
