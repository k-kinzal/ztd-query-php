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
        return (new Invocation\Dispatch($this->engine))->apply($frame, $instruction, $depth, fn (string $target, ?Term $receiver, ?Term $callable): Term => $this->expand($frame, $instruction, $target, $callable, $depth, $receiver));
    }

    /**
     * Expands a selected target after dispatch, applying replacement semantics first.
     */
    public function expand(Frame $frame, Instruction $instruction, string $target, ?Term $callable, int $depth, ?Term $receiver = null): Term
    {
        $context = $this->engine->context;
        $reason = $context->boundary($depth);
        if ($reason !== null) {
            return $context->reference($frame, 'call:' . $target, $instruction->source, reason: $reason, kind: 'deferred');
        }
        $context->referenceExpansions++;
        $body = (new Invocation\Bodies($this->engine))->select($frame, $instruction, $target, $depth - 1);
        $graph = $body->implementation;
        if ($graph instanceof Term) {
            return $graph;
        }
        if ($graph !== null && !$body->replacement) {
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
        if (($frame->calls[$instruction->id] ?? 0) >= $context->budget->recursion || ($frame->calls[$instruction->id] ?? 0) > 0 && $target === $frame->graph->body->symbol && $this->passThroughRecursion($frame, $instruction)) {
            return $context->reference($frame, 'call:' . $target, $instruction->source, $graph->body->returnType, ($frame->calls[$instruction->id] ?? 0) >= $context->budget->recursion ? 'RECURSION_LIMIT' : 'CYCLE', 'recursive');
        }
        $body->enter($context);
        $bound = $this->bind($frame, $instruction, $graph, $receiver);
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
     * Proves pass-through bindings only when their storage cannot have changed.
     */
    public function passThroughRecursion(Frame $frame, Instruction $call): bool
    {
        if ($call->operation !== 'invoke' || count($call->arguments) !== count($frame->graph->body->parameters) || $call->arguments === []) {
            return false;
        }
        if (!$this->stableEnvironment($frame)) {
            return false;
        }
        foreach ($call->arguments as $position => $argument) {
            $value = $frame->graph->definitions[$argument->register] ?? null;
            $address = $value?->operation === 'argument' && ($value->attributes['address'] ?? false) === true ? ($frame->graph->definitions[$value->operands[1]] ?? null) : null;
            if ($address?->operation !== 'local' || $address->name !== ($argument->name ?? $frame->graph->body->parameters[$position]->name ?? null) || (new Storage($this->engine))->modified($frame, $address->name)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Rejects hidden mutable inputs before proving recursive bindings unchanged.
     */
    public function stableEnvironment(Frame $frame): bool
    {
        foreach ($frame->graph->definitions as $definition) {
            if (Memory\Mutations::writes($definition) && Memory\Mutations::root($frame->graph, $definition)?->operation !== 'local') {
                return false;
            }
            if (in_array($definition->operation, ['global', 'static-local', 'invoke-method', 'invoke-static', 'new', 'alias', 'external', 'external-body'], true) || $definition->operation === 'invoke' && $this->engine->context->index->target($frame->graph, $definition) !== $frame->graph->body->symbol) {
                return false;
            }
        }
        return true;
    }

    /**
     * Maps actual arguments to lazy formal bindings.
     */
    public function bind(Frame $caller, Instruction $call, Graph $graph, ?Term $receiver = null): Frame
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
            $bindings['this'] = $receiver ?? new Binding($caller, $call->operands[0]);
        } elseif ($call->operation === 'new') {
            $class = $this->engine->context->index->className($this->engine->context->index->literal($caller->graph, $call->operands[0]), $caller->graph->body->className);
            $bindings['this'] = new Term('object', $caller->identity . ':' . $call->id, attributes: ['type' => $class, 'class' => $class, 'allocation' => $call->result, 'context' => $caller->identity]);
        }
        $calls = $caller->calls;
        $calls[$call->id] = ($calls[$call->id] ?? 0) + 1;
        $identity = 'call:' . hash('sha256', $caller->identity . ':' . $call->id . ':' . $graph->body->symbol . ':' . $graph->body->source->path . ':' . ($receiver === null ? '' : (new \Deriver\Value\Identity())->key($receiver)));
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
        return (new Invocation\Dispatch($this->engine))->apply($caller, $call, $depth, fn (string $target, ?Term $receiver): Term => $this->selectedEffect($caller, $call, $address, $actual, $target, $receiver, $depth) ?? $this->engine->value($caller, $actual, $depth));
    }

    /**
     * Resolves a reference write from the selected implementation.
     */
    public function selectedEffect(Frame $caller, Instruction $call, string $address, string $actual, string $target, ?Term $receiver, int $depth): ?Term
    {
        $body = (new Invocation\Bodies($this->engine))->select($caller, $call, $target, $depth);
        $graph = $body->implementation;
        if ($graph instanceof Term) {
            return new Term('call-write', $target, [$this->engine->value($caller, $actual, $depth), $graph]);
        }
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
            $bound = $this->bind($caller, $call, $graph, $receiver);
            foreach ($graph->definitions as $definition) {
                if ($definition->operation === 'local' && $definition->name === $parameter->name) {
                    $body->enter($this->engine->context);
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
