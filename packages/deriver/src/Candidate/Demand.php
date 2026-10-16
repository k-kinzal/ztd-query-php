<?php

declare(strict_types=1);

namespace Deriver\Candidate;

use Deriver\ControlFlow\Instruction;
use Deriver\Query\ParameterQuery;
use Deriver\Query\Query;
use Deriver\Query\ReturnQuery;
use Deriver\Query\StateQuery;
use Deriver\Query\TupleQuery;
use Deriver\Query\ValueQuery;
use Deriver\Value\Term;

/**
 * Places a query's demand on the engine: the selected expression, return, tuple, or storage definition in each entry frame.
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
    public function select(string $symbol, Query $query): Term
    {
        $graph = $this->engine->context->index->graph($symbol);
        if ($graph === null) {
            return (new Choices())->make([[new Term('reference', $symbol, attributes: ['reason' => 'MISSING_SOURCE', 'scope' => $symbol]), []]]);
        }
        $rows = [];
        foreach ($this->frames($graph, $query) as $frame) {
            $rows[] = [$this->observe($frame, $query), []];
        }
        return (new Choices())->make($rows);
    }

    /**
     * Builds the entry frames the query scope declares for a graph, or one source frame without explicit bindings.
     * @return list<Frame>
     */
    public function frames(Graph $graph, Query $query): array
    {
        $frames = [];
        foreach ($query->scope()->entries as $entry) {
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
    public function observe(Frame $frame, Query $query): Term
    {
        $depth = $query->budget()->maxDepth;
        if ($query instanceof ParameterQuery) {
            $parameter = array_values(array_filter($frame->graph->body->parameters, static fn ($parameter): bool => $parameter->name === $query->parameter))[0];
            $local = new Instruction('parameter:' . $query->parameter, 'local', $parameter->source ?? $frame->graph->body->source, name: $query->parameter);
            return $this->tuple(['value' => (new Origins($this->engine))->parameter($frame, $local, $depth)]);
        }
        if ($query instanceof ReturnQuery) {
            return $this->returns($frame, $depth);
        }
        if ($query instanceof ValueQuery) {
            $value = $this->engine->value($frame, $query->expression->register, $depth);
            if ($query->projection->slot !== null) {
                [$block, $offset] = $frame->graph->positions[$query->expression->register];
                $value = (new Memory\Slots($this->engine))->before($frame, $value, $query->projection->slot, $block, $offset, $depth);
            }
            foreach ($query->projection->path as $key) {
                $value = $this->engine->operation('array-read', '', [$value, Term::constant($key)]);
            }
            return $this->tuple(['value' => $value]);
        }
        if ($query instanceof TupleQuery) {
            $values = [];
            foreach ($query->values as $name => $reference) {
                $values[$name] = $this->engine->value($frame, $reference->register, $depth);
            }
            return $this->tuple($values);
        }
        if ($query instanceof StateQuery) {
            $register = $frame->graph->instructions[$query->point->instruction];
            [$block, $offset] = $frame->graph->positions[$register];
            foreach ($frame->graph->definitions as $definition) {
                if ($definition->operation === 'local' && $definition->name === $query->variable) {
                    $value = (new Storage($this->engine))->search($frame, $definition->result, $block, $offset + ($query->point->phase === 'after' ? 1 : 0), $depth);
                    if ($query->projection->slot !== null) {
                        $value = (new Memory\Slots($this->engine))->before($frame, $value, $query->projection->slot, $block, $offset + ($query->point->phase === 'after' ? 1 : 0), $depth);
                    }
                    foreach ($query->projection->path as $key) {
                        $value = $this->engine->operation('array-read', '', [$value, Term::constant($key)]);
                    }
                    return $this->tuple(['state' => $value]);
                }
            }
        }
        return $this->tuple(['value' => new Term('reference', 'observation', attributes: ['reason' => 'UNRESOLVED_OBSERVATION'])]);
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
