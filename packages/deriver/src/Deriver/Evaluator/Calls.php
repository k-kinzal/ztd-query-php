<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator;

use Closure;
use Deriver\Deriver\Evaluator\Index\BodyIndex;
use Deriver\Deriver\Lowerer\Graph\Instruction;

/**
 * Requests return or write dependencies with lazy argument bindings.
 * @visibility namespace
 */
final class Calls
{
    /**
     * Captures the dependencies used by this component.
     */
    public function __construct(public readonly Evaluator $engine, private readonly bool $truth = false, private readonly int $wanted = 3, private readonly ?Enumeration\Finite\Demand $finite = null)
    {
    }

    /**
     * Expands the requested dependency and retains unresolved children.
     */
    public function value(Scope\Frame $frame, Instruction $instruction, int $depth): Term
    {
        return (new Invocation\Dispatch($this->engine))->apply($frame, $instruction, $depth, fn (string $target, ?Term $receiver, ?Term $callable): Term => $this->expand($frame, $instruction, $target, $callable, $depth, $receiver));
    }

    /**
     * Expands a selected target after dispatch, applying replacement semantics first.
     */
    public function expand(Scope\Frame $frame, Instruction $instruction, string $target, ?Term $callable, int $depth, ?Term $receiver = null, bool $variants = true): Term
    {
        $context = $this->engine->context;
        $reason = $context->boundary($depth);
        if ($reason !== null) {
            return (new Enumeration\Suspension())->expression($context, $frame, $instruction, $reason);
        }
        $context->referenceExpansions++;
        $body = (new Invocation\Bodies($this->engine))->select($frame, $instruction, $target, $depth - 1);
        if ($variants && !$body->replacement && $context->index->program->variants($target) !== []) {
            $names = [$target, ...$context->index->program->variants($target)];
            $branches = array_map(fn (string $variant): Closure => fn (): Term => $this->expand($frame, $instruction, $variant, $callable, $depth, $receiver, false), $names);
            return (new Enumeration\Choices($context->provenance))->make(array_map(static fn (Term $value, string $variant): array => [$value, ['declaration:' . $target => $variant]], $context->search->all($branches), $names));
        }
        $graph = $body->implementation;
        if ($graph instanceof Term) {
            return $graph;
        }
        if ($graph !== null && !$body->replacement && isset($context->evaluationEvidence[$frame->graph->body])) {
            $error = $this->signature($frame, $instruction, $graph, $depth - 1);
            if ($error !== null) {
                return $error;
            }
        }
        if ($instruction->operation === 'new') {
            $class = $context->index->className($context->index->literal($frame->graph, $instruction->operands[0]), $frame->graph->body->className, $frame->calledClass);
            return new Term(TermKind::Object, $frame->identity . ':' . $instruction->id, attributes: ['type' => $class, 'class' => $class, 'allocation' => $instruction->result, 'context' => $frame->identity]);
        }
        return $this->invoke($frame, $instruction, $target, $callable, $depth, $receiver, $body);
    }

    /**
     * Opens the selected implementation with its receiver and recursive bindings.
     */
    public function invoke(Scope\Frame $frame, Instruction $instruction, string $target, ?Term $callable, int $depth, ?Term $receiver, Invocation\Body $body): Term
    {
        $context = $this->engine->context;
        $graph = $body->implementation;
        if (!$graph instanceof BodyIndex) {
            return $graph ?? $this->missing($frame, $instruction, $target, $receiver, $depth);
        }
        if (!(new \Deriver\Deriver\Lowerer\Hierarchy\Access($context->index->program))->allows($graph->body, $frame->graph->body->className)) {
            return new Term(TermKind::Throwable, 'Error', attributes: ['source' => $instruction->source->path, 'start' => $instruction->source->start]);
        }
        if ($graph->body->external || $graph->body->abstract) {
            return $this->missing($frame, $instruction, $target, $receiver, $depth);
        }
        if (($frame->calls[$instruction->id] ?? 0) >= $context->budget->recursion || ($frame->calls[$instruction->id] ?? 0) > 0 && $target === $frame->graph->body->symbol && $this->passThroughRecursion($frame, $instruction)) {
            return (new Enumeration\Suspension())->expression($context, $frame, $instruction, ($frame->calls[$instruction->id] ?? 0) >= $context->budget->recursion ? 'RECURSION_LIMIT' : 'CYCLE');
        }
        $body->enter($context);
        $bound = $this->bind($frame, $instruction, $graph, $receiver, $depth - 1);
        if ($callable?->kind === TermKind::Closure) {
            $bound = $this->captures($bound, $callable);
        }
        return $this->engine->context->provenance->wrap((new Expression\ReturnEvaluator($this->engine))->evaluate($bound, $depth - 1, $this->truth, $this->wanted, $this->finite), Evidence\EvidenceKind::Call, $instruction->source, ['caller' => $frame->graph->body->symbol, 'callee' => $graph->body->symbol, 'call_site' => $instruction->source->id(), 'parent_context' => $frame->identity, 'context' => $bound->identity, 'called_class' => $bound->calledClass, 'lexical_class' => $graph->body->className, 'receiver' => $receiver === null ? '' : (new Scalar\Identity())->key($receiver), 'implementation' => $graph->body->source->id()]);
    }

    /**
     * Retains demanded inputs when the selected implementation has no captured body.
     */
    public function missing(Scope\Frame $frame, Instruction $instruction, string $target, ?Term $receiver, int $depth): Term
    {
        $arguments = $this->engine->context->search->all(array_map(fn ($argument): Closure => fn (): Term => $this->engine->value($frame, $argument->register, $depth - 1), $instruction->arguments));
        $arguments = $receiver === null ? $arguments : [$receiver, ...$arguments];
        return (new Language\Expressions($this->engine))->enumCases($target) ?? new Term(TermKind::Call, $target, $arguments, ['source' => $instruction->source->path, 'start' => $instruction->source->start, 'reason' => 'MISSING_SOURCE']);
    }

    /**
     * Checks declaration constraints that can change the requested call result.
     */
    public function signature(Scope\Frame $caller, Instruction $call, BodyIndex $graph, int $depth): ?Term
    {
        $bound = $this->bind($caller, $call, $graph, depth: $depth);
        foreach ($graph->body->parameters as $parameter) {
            if (!isset($bound->bindings[$parameter->name]) && $parameter->default === null && !$parameter->variadic) {
                return new Term(TermKind::Throwable, 'ArgumentCountError');
            }
            if ($parameter->type === 'mixed' || $parameter->variadic) {
                continue;
            }
            $local = new Call\BoundInstruction('input:' . $parameter->name, 'local', $call->source, name: $parameter->name);
            $value = (new Expression\Origins($this->engine))->parameter($bound, $local, $depth);
            if ($value->kind === TermKind::Throwable) {
                return $value;
            }
        }
        return null;
    }

    /**
     * Binds lexical captures at the closure creation position.
     */
    public function captures(Scope\Frame $bound, Term $closure): Scope\Frame
    {
        $owner = $this->engine->context->frames[(string) ($closure->attributes['context'] ?? '')] ?? null;
        if ($owner === null) {
            return $bound;
        }
        $position = $owner->graph->positions[(string) $closure->attributes['creation']];
        $bindings = $bound->bindings;
        foreach ($bound->graph->body->captures as $name => $byReference) {
            foreach ($owner->graph->definitions as $definition) {
                if ($definition->operation === 'local' && $definition->name === $name) {
                    $observation = $byReference && $bound->origin?->frame->identity === $owner->identity ? $owner->graph->positions[$bound->origin->register] : $position;
                    $captured = new Scope\Binding($owner, $definition->result, position: $observation);
                    $bindings[$name] = $byReference && $bound->origin?->frame->identity !== $owner->identity ? new Term(TermKind::CaptureReference, $name, [$captured->value($this->engine, 'mixed', $this->engine->context->budget->maxDepth)], ['reason' => 'UNKNOWN_CAPTURE_HISTORY']) : $captured;
                    break;
                }
            }
        }
        return new Scope\Frame($bound->graph, $bound->identity, $bindings, $bound->properties, $bound->calls, true, origin: $bound->origin, calledClass: $owner->calledClass);
    }

    /**
     * Proves pass-through bindings only when their storage cannot have changed.
     */
    public function passThroughRecursion(Scope\Frame $frame, Instruction $call): bool
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
            if ($address?->operation !== 'local' || $address->name !== ($argument->name ?? $frame->graph->body->parameters[$position]->name ?? null) || (new Expression\Storage($this->engine))->modified($frame, $address->name)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Rejects hidden mutable inputs before proving recursive bindings unchanged.
     */
    public function stableEnvironment(Scope\Frame $frame): bool
    {
        foreach ($frame->graph->definitions as $definition) {
            if (BodyIndex::writes($definition) && $frame->graph->root($definition)?->operation !== 'local') {
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
    public function bind(Scope\Frame $caller, Instruction $call, BodyIndex $graph, ?Term $receiver = null, ?int $depth = null): Scope\Frame
    {
        $bindings = (new Invocation\Arguments($this->engine))->bind($caller, $call, $graph->body->parameters, $depth ?? $this->engine->context->budget->maxDepth);
        if ($call->operation === 'invoke-method' || $receiver !== null) {
            $bindings['this'] = $receiver ?? new Scope\Binding($caller, $call->operands[0]);
        } elseif ($call->operation === 'new') {
            $class = $this->engine->context->index->className($this->engine->context->index->literal($caller->graph, $call->operands[0]), $caller->graph->body->className, $caller->calledClass);
            $bindings['this'] = new Term(TermKind::Object, $caller->identity . ':' . $call->id, attributes: ['type' => $class, 'class' => $class, 'allocation' => $call->result, 'context' => $caller->identity]);
        }
        $calls = $caller->calls;
        $calls[$call->id] = ($calls[$call->id] ?? 0) + 1;
        $identity = 'call:' . hash('sha256', $caller->identity . ':' . $call->id . ':' . $graph->body->symbol . ':' . $graph->body->source->path . ':' . ($receiver === null ? '' : (new Scalar\Identity())->key($receiver)));
        $raw = $this->engine->context->index->literal($caller->graph, $call->operands[0] ?? '');
        $calledClass = (string) ($receiver?->attributes['type'] ?? (in_array(strtolower($raw), ['self', 'parent', 'static'], true) ? $caller->calledClass : $raw));
        return new Scope\Frame($graph, $identity, $bindings, calls: $calls, invocation: true, origin: new Scope\Binding($caller, $call->result), calledClass: $calledClass);
    }

    /**
     * Finds a call write that affects the requested storage location.
     */
    public function effect(Scope\Frame $caller, Instruction $call, string $address, int $depth): ?Term
    {
        $actual = $this->passed($caller, $call, $address);
        if ($actual === null) {
            return (new Memory\Captures())->effect($this->engine, $caller, $call, $address, $depth) ?? (new Memory\PropertyEffects())->effect($this->engine, $caller, $call, $address, $depth) ?? (new Memory\Globals())->effect($this->engine, $caller, $call, $address, $depth);
        }
        $target = $this->engine->context->index->target($caller->graph, $call);
        if ($target !== '' && $this->byValue($call, $target, $actual)) {
            return null;
        }
        [$block, $offset] = $caller->graph->positions[$call->result];
        return (new Invocation\Dispatch($this->engine))->apply($caller, $call, $depth, fn (string $selected, ?Term $receiver): Term => $this->selectedEffect($caller, $call, $address, $actual, $selected, $receiver, $depth) ?? (new Expression\Storage($this->engine))->search($caller, $address, $block, $offset, $depth));
    }

    /**
     * Resolves a reference write from the selected implementation.
     */
    public function selectedEffect(Scope\Frame $caller, Instruction $call, string $address, string $actual, string $target, ?Term $receiver, int $depth): ?Term
    {
        $body = (new Invocation\Bodies($this->engine))->select($caller, $call, $target, $depth);
        $graph = $body->implementation;
        if ($graph instanceof Term) {
            if ($this->byValue($call, $target, $actual)) {
                return null;
            }
            return new Term(TermKind::CallWrite, $target, [$this->engine->value($caller, $actual, $depth), $graph]);
        }
        if ($graph === null) {
            return $this->unknownWrite($caller, $call, $address, $depth);
        }
        foreach ($graph->body->parameters as $position => $parameter) {
            if (!$parameter->byReference) {
                continue;
            }
            $register = (new CallEvaluation($this->engine))->actual($call, $parameter->name, $position);
            $argument = $caller->graph->definitions[$register ?? ''] ?? null;
            $actual = $argument?->operation === 'argument' ? ($argument->operands[1] ?? '') : ($call->arguments[$position]->location ?? '');
            if ((new Expression\Storage($this->engine))->key($caller, $actual) !== (new Expression\Storage($this->engine))->key($caller, $address)) {
                continue;
            }
            if ($graph->body->external) {
                $previous = $this->engine->value($caller, $register ?? '', $depth);
                return new Term(TermKind::CallWrite, $target . ':$' . $parameter->name, [$previous], ['reason' => 'MISSING_SOURCE', 'source' => $call->source->path, 'start' => $call->source->start, 'end' => $call->source->end]);
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
     * Reports whether a standard function with a known signature takes the passed storage by value,
     * in which case the call cannot write it and is not a reaching definition.
     * @param string $actual Argument register passing the storage address
     */
    public function byValue(Instruction $call, string $target, string $actual): bool
    {
        $parameters = (new Builtin\Library())->parameters(strtolower(ltrim($target, '\\')));
        if ($parameters === null) {
            return false;
        }
        foreach ($call->arguments as $position => $argument) {
            if ($argument->register !== $actual) {
                continue;
            }
            foreach ($parameters as $index => $parameter) {
                if ($argument->name !== null ? $parameter->name === $argument->name : $index === $position || $parameter->variadic && $position >= $index) {
                    return !$parameter->byReference;
                }
            }
            return false;
        }
        return false;
    }

    /**
     * Selects a passed storage address before requesting any evaluator inputs or source effects.
     */
    public function passed(Scope\Frame $caller, Instruction $call, string $address): ?string
    {
        foreach ($call->arguments as $argument) {
            $wrapper = $caller->graph->definitions[$argument->register] ?? null;
            if ($wrapper?->operation === 'argument' && ($wrapper->attributes['address'] ?? false) === true && (new Expression\Storage($this->engine))->key($caller, $wrapper->operands[1]) === (new Expression\Storage($this->engine))->key($caller, $address)) {
                return $argument->register;
            }
        }
        return null;
    }

    /**
     * Retains only a passed storage dependency whose reference behavior is unavailable.
     */
    public function unknownWrite(Scope\Frame $caller, Instruction $call, string $address, int $depth): ?Term
    {
        foreach ($call->arguments as $argument) {
            $wrapper = $caller->graph->definitions[$argument->register] ?? null;
            if ($wrapper?->operation !== 'argument' || ($wrapper->attributes['address'] ?? false) !== true) {
                continue;
            }
            if ((new Expression\Storage($this->engine))->key($caller, $wrapper->operands[1]) === (new Expression\Storage($this->engine))->key($caller, $address)) {
                return new Term(TermKind::CallWrite, $this->engine->context->index->target($caller->graph, $call), [$this->engine->value($caller, $argument->register, $depth)], ['reason' => 'MISSING_SIGNATURE', 'source' => $call->source->path, 'start' => $call->source->start]);
            }
        }
        return null;
    }

    /**
     * Collects the requested storage definition at each return.
     */
    public function finalStorage(Scope\Frame $frame, string $address, int $depth): Term
    {
        $values = [];
        foreach ($frame->graph->returns() as [$block, $_]) {
            $value = (new Expression\Storage($this->engine))->search($frame, $address, $block, count($frame->graph->body->blocks[$block]->instructions), $depth);
            $values[] = [(new Expression\Guards($this->engine))->at($frame, $block, $value, $depth), []];
        }
        return $this->engine->context->provenance->evaluator((new Enumeration\Choices($this->engine->context->provenance))->make($values), $this->engine->context->evaluationEvidence[$frame->graph->body] ?? null);
    }
}
