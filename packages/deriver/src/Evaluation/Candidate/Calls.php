<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Candidate;

use Deriver\ControlFlow\Instruction;
use Deriver\Value\Term;

/**
 * Requests return or write dependencies with lazy argument bindings.
 * @visibility root
 */
final class Calls
{
    /**
     * Captures the dependencies used by this component.
     */
    public function __construct(public readonly Derivation $engine)
    {
    }

    /**
     * Expands the requested dependency and retains unresolved children.
     */
    public function value(Frame $frame, Instruction $instruction, int $depth): Term
    {
        $context = $this->engine->context;
        $target = (string) ($instruction->attributes['candidate-target'] ?? $context->index->target($frame->graph, $instruction));
        $callable = null;
        if ($instruction->operation === 'invoke-method' && !isset($instruction->attributes['candidate-target']) && ($target === '' || str_starts_with($target, 'mixed::'))) {
            $receiver = $this->engine->value($frame, $instruction->operands[0], $depth);
            return (new Choices())->apply('method-target', [$receiver], function (array $values) use ($frame, $instruction, $depth, $target): Term {
                $class = (string) ($values[0]->attributes['type'] ?? '');
                $selected = $class === '' ? $target : $this->engine->context->index->method($class, $this->engine->context->index->literal($frame->graph, $instruction->operands[1]));
                $call = new Instruction($instruction->id, $instruction->operation, $instruction->source, $instruction->result, $instruction->operands, $instruction->name, $instruction->constant, $instruction->arguments, [...$instruction->attributes, 'candidate-target' => $selected]);
                return $this->value($frame, $call, $depth);
            }, $context->budget->partitions);
        }
        if ($target === '' && $instruction->operation === 'invoke') {
            $callable = $this->engine->value($frame, $instruction->operands[0], $depth);
            if (in_array($callable->kind, ['constant', 'closure'], true) && is_string($callable->literal)) {
                $target = $callable->literal;
            }
        }
        return $this->expand($frame, $instruction, $target, $callable, $depth);
    }

    /**
     * Expands a selected target after dispatch, applying replacement semantics first.
     */
    public function expand(Frame $frame, Instruction $instruction, string $target, ?Term $callable, int $depth): Term
    {
        $context = $this->engine->context;
        $reason = $context->boundary($depth);
        if ($reason !== null) {
            return $context->reference($frame, 'call:' . $target, $instruction->source, reason: $reason, kind: 'deferred');
        }
        $context->referenceExpansions++;
        $model = (new Models($this->engine))->graph($frame, $instruction, $target, $depth - 1);
        if ($model instanceof Term) {
            return $model;
        }
        $graph = $model ?? $context->index->graph($target);
        if ($graph !== null && $model === null) {
            $error = $this->signature($frame, $instruction, $graph, $depth - 1);
            if ($error !== null) {
                return $error;
            }
        }
        if ($instruction->operation === 'new') {
            $class = $context->index->className($context->index->literal($frame->graph, $instruction->operands[0]), $frame->graph->body->className);
            return new Term('object', $frame->identity . ':' . $instruction->id, attributes: ['type' => $class, 'class' => $class, 'allocation' => $instruction->result, 'context' => $frame->identity]);
        }
        if ($graph !== null && !(new \Deriver\Evaluation\Call\Member\Access($context->index->program))->allows($graph->body, $frame->graph->body->className)) {
            return new Term('throwable', 'Error', attributes: ['source' => $instruction->source->path, 'start' => $instruction->source->start]);
        }
        if ($graph === null || $graph->body->external) {
            $arguments = array_map(fn ($argument): Term => $this->engine->value($frame, $argument->register, $depth - 1), $instruction->arguments);
            return new Term('call', $target, $arguments, ['source' => $instruction->source->path, 'start' => $instruction->source->start, 'reason' => 'MISSING_SOURCE']);
        }
        if (($frame->calls[$instruction->id] ?? 0) >= $context->budget->recursion || ($frame->calls[$instruction->id] ?? 0) > 0 && $this->unchangedArguments($frame, $instruction)) {
            return $context->reference($frame, 'call:' . $target, $instruction->source, $graph->body->returnType, 'CYCLE', 'recursive');
        }
        if ($model === null) {
            $context->bodyExpansions++;
            $context->bodies[$target] = ($context->bodies[$target] ?? 0) + 1;
        }
        $bound = $this->bind($frame, $instruction, $graph);
        if ($callable?->kind === 'closure') {
            $bound = $this->captures($bound, $callable);
        }
        return $this->engine->returns($bound, $depth - 1);
    }

    /**
     * Checks declaration constraints that can change the requested call result.
     */
    public function signature(Frame $caller, Instruction $call, Graph $graph, int $depth): ?Term
    {
        $bound = $this->bind($caller, $call, $graph);
        foreach ($graph->body->parameters as $parameter) {
            if (!isset($bound->bindings[$parameter->name]) && $parameter->default === null && !$parameter->variadic) {
                return new Term('throwable', 'ArgumentCountError');
            }
            if ($parameter->type === 'mixed' || $parameter->variadic) {
                continue;
            }
            $local = new Instruction('input:' . $parameter->name, 'local', $call->source, name: $parameter->name);
            $value = (new Origins($this->engine))->parameter($bound, $local, $depth);
            if ($value->kind === 'throwable') {
                return $value;
            }
        }
        return null;
    }

    /**
     * Binds lexical captures at the closure creation position.
     */
    public function captures(Frame $bound, Term $closure): Frame
    {
        $owner = $this->engine->context->frames[(string) ($closure->attributes['context'] ?? '')] ?? null;
        if ($owner === null) {
            return $bound;
        }
        $position = $owner->graph->positions[(string) $closure->attributes['creation']];
        $bindings = $bound->bindings;
        foreach ($bound->graph->body->captures as $name => $_) {
            foreach ($owner->graph->definitions as $definition) {
                if ($definition->operation === 'local' && $definition->name === $name) {
                    $bindings[$name] = new Binding($owner, $definition->result, position: $position);
                    break;
                }
            }
        }
        return new Frame($bound->graph, $bound->identity, $bindings, $bound->properties, $bound->calls, true, origin: $bound->origin);
    }

    /**
     * Recognizes a repeated call with unchanged positional parameters.
     */
    public function unchangedArguments(Frame $frame, Instruction $call): bool
    {
        foreach ($call->arguments as $position => $argument) {
            $value = $frame->graph->definitions[$argument->register] ?? null;
            $address = $value?->operation === 'argument' && ($value->attributes['address'] ?? false) === true ? ($frame->graph->definitions[$value->operands[1]] ?? null) : null;
            if ($address?->operation !== 'local' || $address->name !== ($frame->graph->body->parameters[$position]->name ?? null)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Maps actual arguments to lazy formal bindings.
     */
    public function bind(Frame $caller, Instruction $call, Graph $graph): Frame
    {
        $bindings = [];
        foreach ($graph->body->parameters as $position => $parameter) {
            $actual = (new Models($this->engine))->actual($call, $parameter->name, $position);
            if ($parameter->variadic) {
                $registers = array_map(static fn ($argument): string => $argument->register, array_slice($call->arguments, $position));
                $bindings[$parameter->name] = new Binding($caller, '', $registers);
            } elseif ($actual !== null) {
                $bindings[$parameter->name] = new Binding($caller, $actual);
            }
        }
        if ($call->operation === 'invoke-method') {
            $bindings['this'] = new Binding($caller, $call->operands[0]);
        } elseif ($call->operation === 'new') {
            $bindings['this'] = new Term('object', $caller->identity . ':' . $call->id, attributes: ['type' => $graph->body->className, 'class' => $graph->body->className, 'allocation' => $call->result, 'context' => $caller->identity]);
        }
        $calls = $caller->calls;
        $calls[$call->id] = ($calls[$call->id] ?? 0) + 1;
        $identity = 'call:' . hash('sha256', $caller->identity . ':' . $call->id . ':' . $graph->body->symbol . ':' . $graph->body->source->path);
        return new Frame($graph, $identity, $bindings, calls: $calls, invocation: true, origin: new Binding($caller, $call->result));
    }

    /**
     * Finds a call write that affects the requested storage location.
     */
    public function effect(Frame $caller, Instruction $call, string $address, int $depth): ?Term
    {
        $actual = $this->passed($caller, $call, $address);
        if ($actual === null) {
            return null;
        }
        $target = $this->engine->context->index->target($caller->graph, $call);
        $model = (new Models($this->engine))->graph($caller, $call, $target, $depth);
        if ($model instanceof Term) {
            return new Term('call-write', $target, [$this->engine->value($caller, $actual, $depth), $model]);
        }
        $graph = $model ?? $this->engine->context->index->graph($target);
        if ($graph === null) {
            return $this->unknownWrite($caller, $call, $address, $depth);
        }
        foreach ($graph->body->parameters as $position => $parameter) {
            if (!$parameter->byReference) {
                continue;
            }
            $register = (new Models($this->engine))->actual($call, $parameter->name, $position);
            $argument = $caller->graph->definitions[$register ?? ''] ?? null;
            $actual = $argument?->operation === 'argument' ? ($argument->operands[1] ?? '') : ($call->arguments[$position]->location ?? '');
            if ((new Storage($this->engine))->key($caller, $actual) !== (new Storage($this->engine))->key($caller, $address)) {
                continue;
            }
            if ($graph->body->external) {
                $previous = $this->engine->value($caller, $register ?? '', $depth);
                return new Term('call-write', $target . ':$' . $parameter->name, [$previous], ['reason' => 'MISSING_SOURCE', 'source' => $call->source->path, 'start' => $call->source->start, 'end' => $call->source->end]);
            }
            $bound = $this->bind($caller, $call, $graph);
            foreach ($graph->definitions as $definition) {
                if ($definition->operation === 'local' && $definition->name === $parameter->name) {
                    if ($model === null) {
                        $this->engine->context->bodyExpansions++;
                        $this->engine->context->bodies[$target] = ($this->engine->context->bodies[$target] ?? 0) + 1;
                    }
                    return $this->finalStorage($bound, $definition->result, $depth);
                }
            }
        }
        return null;
    }

    /**
     * Selects a passed storage address before requesting any model inputs or source effects.
     */
    public function passed(Frame $caller, Instruction $call, string $address): ?string
    {
        foreach ($call->arguments as $argument) {
            $wrapper = $caller->graph->definitions[$argument->register] ?? null;
            if ($wrapper?->operation === 'argument' && ($wrapper->attributes['address'] ?? false) === true && (new Storage($this->engine))->key($caller, $wrapper->operands[1]) === (new Storage($this->engine))->key($caller, $address)) {
                return $argument->register;
            }
        }
        return null;
    }

    /**
     * Retains only a passed storage dependency whose reference behavior is unavailable.
     */
    public function unknownWrite(Frame $caller, Instruction $call, string $address, int $depth): ?Term
    {
        foreach ($call->arguments as $argument) {
            $wrapper = $caller->graph->definitions[$argument->register] ?? null;
            if ($wrapper?->operation !== 'argument' || ($wrapper->attributes['address'] ?? false) !== true) {
                continue;
            }
            if ((new Storage($this->engine))->key($caller, $wrapper->operands[1]) === (new Storage($this->engine))->key($caller, $address)) {
                return new Term('call-write', $this->engine->context->index->target($caller->graph, $call), [$this->engine->value($caller, $argument->register, $depth)], ['reason' => 'MISSING_SIGNATURE', 'source' => $call->source->path, 'start' => $call->source->start]);
            }
        }
        return null;
    }

    /**
     * Collects the requested storage definition at each return.
     */
    public function finalStorage(Frame $frame, string $address, int $depth): Term
    {
        $values = [];
        foreach ($frame->graph->returns() as [$block, $_]) {
            $value = (new Storage($this->engine))->search($frame, $address, $block, count($frame->graph->body->blocks[$block]->instructions), $depth);
            $values[] = [(new Guards($this->engine))->at($frame, $block, $value, $depth), []];
        }
        return (new Choices())->make($values);
    }
}
