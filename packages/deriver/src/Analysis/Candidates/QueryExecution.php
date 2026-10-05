<?php

declare(strict_types=1);

namespace Deriver\Analysis\Candidates;

use Deriver\Analysis\QueryValidation;
use Deriver\Evaluation\Candidate\Cache;
use Deriver\Evaluation\Candidate\Choices;
use Deriver\Evaluation\Candidate\Context;
use Deriver\Evaluation\Candidate\Derivation;
use Deriver\Evaluation\Candidate\Frame;
use Deriver\Evaluation\Candidate\Graph;
use Deriver\Evaluation\Candidate\Index;
use Deriver\Evaluation\Candidate\Storage;
use Deriver\Model\Registration\Registry;
use Deriver\Project\Configuration;
use Deriver\Project\ProjectSnapshot;
use Deriver\Query\Query;
use Deriver\Query\ReturnQuery;
use Deriver\Query\StateQuery;
use Deriver\Query\TupleQuery;
use Deriver\Query\ValueQuery;
use Deriver\Result\Candidates\CandidateCollection;
use Deriver\Value\Term;
use JsonException;

/**
 * Starts each query at its selected expressions and adapts the immutable candidate graph.
 * @visibility root
 */
final class QueryExecution
{
    /**
     * Captures the dependencies used by this component.
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
        foreach ($query->scope()->entries as $entry) {
            $entryGraph = $this->index->graph($entry->symbol);
            if ($entryGraph !== null) {
                $context->entryFrames[strtolower($entry->symbol)] = $this->frames($entryGraph, $query);
            }
        }
        $engine = new Derivation($context);
        $graph = $this->index->graph($symbol);
        $rows = [];
        if ($graph === null) {
            $rows[] = [new Term('reference', $symbol, attributes: ['reason' => 'MISSING_SOURCE', 'scope' => $symbol]), []];
        } else {
            foreach ($this->frames($graph, $query) as $frame) {
                $rows[] = [$this->observe($engine, $frame, $query), []];
            }
        }
        return (new ResultBuilder())->build($query, (new Choices())->make($rows), $context, $this->snapshot, $start);
    }

    /**

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
    public function observe(Derivation $engine, Frame $frame, Query $query): Term
    {
        $depth = $query->budget()->maxDepth;
        if ($query instanceof \Deriver\Query\ParameterQuery) {
            $parameter = array_values(array_filter($frame->graph->body->parameters, static fn ($parameter): bool => $parameter->name === $query->parameter))[0];
            $local = new \Deriver\ControlFlow\Instruction('parameter:' . $query->parameter, 'local', $parameter->source ?? $frame->graph->body->source, name: $query->parameter);
            return $this->tuple(['value' => (new \Deriver\Evaluation\Candidate\Origins($engine))->parameter($frame, $local, $depth)], $engine);
        }
        if ($query instanceof ReturnQuery) {
            return $this->returns($engine, $frame, $depth);
        }
        if ($query instanceof ValueQuery) {
            $value = $engine->value($frame, $query->expression->register, $depth);
            if ($query->projection->slot !== null) {
                [$block, $offset] = $frame->graph->positions[$query->expression->register];
                $value = (new \Deriver\Evaluation\Candidate\Memory\Slots($engine))->before($frame, $value, $query->projection->slot, $block, $offset, $depth);
            }
            foreach ($query->projection->path as $key) {
                $value = $engine->operation('array-read', '', [$value, Term::constant($key)]);
            }
            return $this->tuple(['value' => $value], $engine);
        }
        if ($query instanceof TupleQuery) {
            $values = [];
            foreach ($query->values as $name => $reference) {
                $values[$name] = $engine->value($frame, $reference->register, $depth);
            }
            return $this->tuple($values, $engine);
        }
        if ($query instanceof StateQuery) {
            $register = $frame->graph->instructions[$query->point->instruction];
            [$block, $offset] = $frame->graph->positions[$register];
            foreach ($frame->graph->definitions as $definition) {
                if ($definition->operation === 'local' && $definition->name === $query->variable) {
                    $value = (new Storage($engine))->search($frame, $definition->result, $block, $offset + ($query->point->phase === 'after' ? 1 : 0), $depth);
                    if ($query->projection->slot !== null) {
                        $value = (new \Deriver\Evaluation\Candidate\Memory\Slots($engine))->before($frame, $value, $query->projection->slot, $block, $offset + ($query->point->phase === 'after' ? 1 : 0), $depth);
                    }
                    foreach ($query->projection->path as $key) {
                        $value = $engine->operation('array-read', '', [$value, Term::constant($key)]);
                    }
                    return $this->tuple(['state' => $value], $engine);
                }
            }
        }
        return $this->tuple(['value' => new Term('reference', 'observation', attributes: ['reason' => 'UNRESOLVED_OBSERVATION'])], $engine);
    }

    /**
     * Combines conditional declarations using the same return expansion.
     */
    public function returns(Derivation $engine, Frame $frame, int $depth): Term
    {
        $values = [[$engine->returns($frame, $depth), []]];
        foreach ($this->index->program->variants($frame->graph->body->symbol) as $variant) {
            $graph = $this->index->graph($variant);
            if ($graph !== null) {
                $values[] = [$engine->returns(new Frame($graph, 'source:' . $variant, $frame->bindings, $frame->properties, $frame->calls, $frame->invocation, calledClass: $frame->calledClass), $depth), []];
            }
        }
        return $this->tuple(['return' => (new Choices())->make($values)], $engine);
    }

    /**

     * @param array<string, Term> $values

     */
    public function tuple(array $values, Derivation $engine): Term
    {
        $names = array_keys($values);
        $result = (new Choices())->apply('tuple', array_values($values), static fn (array $items): Term => new Term('tuple', operands: array_combine($names, $items)), $engine->context->budget->partitions);
        return $result->kind === 'operation' ? new Term('tuple', operands: $values, attributes: ['reason' => 'ENUMERATION_LIMIT']) : $result;
    }
}
