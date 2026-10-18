<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Expression;

use Deriver\Deriver\Evaluator\Index\BodyIndex;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Lowerer\Graph\Instruction;

/**
 * Follows reaching writes backwards; unrelated instructions are only inspected as index entries.
 * @visibility parent
 */
final class Storage
{
    /**
     * Captures the dependencies used by this component.
     */
    public function __construct(public readonly \Deriver\Deriver\Evaluator\Evaluator $engine, private readonly bool $truth = false, private readonly int $wanted = 3, private readonly ?\Deriver\Deriver\Evaluator\Enumeration\Finite\Demand $finite = null)
    {
    }

    /**
     * Follows the reaching definition for the requested read.
     */
    public function read(\Deriver\Deriver\Evaluator\Scope\Frame $frame, Instruction $read, string $address, int $depth): Term
    {
        $block = $frame->graph->positions[$read->result][0] ?? null;
        if ($block !== null) {
            $frame = (new \Deriver\Deriver\Evaluator\Recurrence\Observation($this->engine))->frame($frame, $block, $depth);
        }
        $definition = $frame->graph->definitions[$address] ?? $read;
        $name = $definition->operation === 'local' ? '$' . $definition->name : $definition->operation . ':' . $address;
        $context = $this->engine->context;
        $type = $this->type($frame, $definition);
        $reason = $context->boundary($depth);
        if ($reason !== null) {
            return $context->reference($frame, $name, $read->source, $type, $reason, \Deriver\Deriver\Evaluator\TermKind::Deferred);
        }
        $context->referenceExpansions++;
        $context->references[$frame->graph->body->symbol . ':' . $name] = ($context->references[$frame->graph->body->symbol . ':' . $name] ?? 0) + 1;
        if ($definition->operation === 'field-address') {
            $hook = (new \Deriver\Deriver\Evaluator\Memory\PropertyHooks())->read($this->engine, $frame, $read, $definition, $depth);
            if ($hook !== null) {
                return $hook;
            }
        }
        if ($definition->operation === 'local' && !$this->modified($frame, $definition->name)) {
            return $this->initial($frame, $address, $depth - 1);
        }
        [$block, $offset] = $frame->graph->positions[$read->result];
        if ($definition->operation === 'element-address') {
            $parent = $this->search($frame, $definition->operands[0], $block, $offset, $depth - 1);
            $key = $this->engine->value($frame, $definition->operands[1], $depth - 1);
            return $this->engine->operation('array-read', '', [$parent, $key]);
        }
        return $this->search($frame, $address, $block, $offset, $depth - 1);
    }

    /**
     * Checks whether a local has relevant writes or reference escapes.
     */
    public function modified(\Deriver\Deriver\Evaluator\Scope\Frame $frame, string $name): bool
    {
        foreach ($frame->graph->definitions as $instruction) {
            $address = $frame->graph->definitions[$instruction->operands[0] ?? ''] ?? null;
            if ($instruction->operation === 'alias' || in_array($instruction->operation, ['write', 'increment', 'compound', 'unset', 'global', 'static-local'], true) && (in_array($address?->operation, ['element-address', 'dynamic-local'], true) || $address?->operation === 'local' && $address->name === $name)) {
                return true;
            }
            foreach ($instruction->arguments as $position => $argument) {
                if (!in_array($instruction->operation, ['invoke', 'invoke-method', 'invoke-static'], true)) {
                    continue;
                }
                $wrapper = $frame->graph->definitions[$argument->register] ?? null;
                $local = $frame->graph->definitions[$wrapper?->operands[1] ?? ''] ?? null;
                if ($local?->operation !== 'local' || $local->name !== $name) {
                    continue;
                }
                $target = $this->engine->context->index->target($frame->graph, $instruction);
                $body = $this->engine->context->index->graph($target)?->body;
                if ($instruction->operation === 'invoke-method' || $body === null || ($body->parameters[$position]->byReference ?? true)) {
                    return true;
                }
            }
        }
        return (new \Deriver\Deriver\Evaluator\Memory\Captures())->contains($this->engine, $frame, $name);
    }

    /**

     * @param array<string, true> $seen

     */
    public function search(\Deriver\Deriver\Evaluator\Scope\Frame $frame, string $address, int $block, int $offset, int $depth, array $seen = []): Term
    {
        $key = ($this->truth ? 'truth-storage:' . $this->wanted . ':' . ($this->finite->identity ?? '') . ':' : 'storage:') . $frame->identity . ':' . $address . ':' . $block . ':' . $offset;
        return $this->engine->context->search->resolve(
            $key,
            fn (): Term => $this->lookup($frame, $address, $block, $offset, $depth, $seen),
            fn (): Term => $this->engine->context->reference($frame, $address, $frame->graph->body->source, reason: 'CYCLE', kind: \Deriver\Deriver\Evaluator\TermKind::Recursive),
        );
    }

    /**
     * Reads the indexed definitions for one scheduled storage dependency.
     * @param array<string, true> $seen Definitions already on this dependency path
     */
    public function lookup(\Deriver\Deriver\Evaluator\Scope\Frame $frame, string $address, int $block, int $offset, int $depth, array $seen = []): Term
    {
        $definition = $frame->graph->definitions[$address] ?? null;
        if ($definition?->operation === 'element-address') {
            return $this->engine->operation('array-read', '', [$this->search($frame, $definition->operands[0], $block, $offset, $depth, $seen), $this->engine->value($frame, $definition->operands[1], $depth)]);
        }
        $key = ($this->truth ? 'truth:' . $this->wanted . ':' . ($this->finite->identity ?? '') . ':' : '') . $frame->identity . ':' . $this->key($frame, $address) . ':' . $block . ':' . $offset;
        if (isset($this->engine->context->storage[$key])) {
            $this->engine->context->sharedNodeHits++;
            return $this->engine->context->storage[$key];
        }
        if (isset($seen[$key])) {
            return $this->engine->context->reference($frame, $this->key($frame, $address), $frame->graph->body->source, reason: 'CYCLE', kind: \Deriver\Deriver\Evaluator\TermKind::Recursive);
        }
        $seen[$key] = true;
        $value = $this->reaching($frame, $address, $block, $offset, $depth, $seen);
        return $this->engine->context->storage[$key] = $value;
    }

    /**

     * @param array<string, true> $seen

     */
    public function reaching(\Deriver\Deriver\Evaluator\Scope\Frame $frame, string $address, int $block, int $offset, int $depth, array $seen): Term
    {
        $instructions = $frame->graph->body->blocks[$block]->instructions;
        for ($index = $offset - 1; $index >= 0; $index--) {
            $write = $instructions[$index];
            if (($reason = $this->engine->context->work()) !== null) {
                return $this->engine->context->reference($frame, $this->key($frame, $address), $write->source, reason: $reason, kind: \Deriver\Deriver\Evaluator\TermKind::Deferred);
            }
            $value = $this->write($frame, $address, $write, $block, $index, $depth, $seen);
            if ($value !== null) {
                return $value;
            }
        }
        $parents = (new \Deriver\Deriver\Evaluator\Recurrence\Definitions($this->engine))->parents($frame, $block);
        if ($frame->graph->body->blocks[$block]->loopHeader) {
            return (new \Deriver\Deriver\Evaluator\Recurrence\Definitions($this->engine))->value($frame, $address, $block, $depth);
        }
        if ($parents === []) {
            return $this->initial($frame, $address, $depth);
        }
        $guarded = $this->branchesWriteLocal($frame, $address, $parents);
        $branches = [];
        $sources = [];
        foreach ($parents as $parent) {
            $source = (new \Deriver\Deriver\Evaluator\Recurrence\Definitions($this->engine))->predecessor($frame, $parent, $block);
            $sources[] = $source;
            $branches[] = function (int $wanted = 3, ?\Deriver\Deriver\Evaluator\Enumeration\Finite\Demand $finite = null) use ($source, $address, $parent, $frame, $depth, $seen, $guarded, $block): Term {
                $guards = new Guards($this->engine);
                if ($guarded && ($this->engine->context->configuration->hasBranchEvaluators || isset($this->engine->context->evaluationEvidence[$source->graph->body]))) {
                    $condition = $guards->at($source, $parent, $guards->edge($source, $parent, $block, Term::constant(true), $depth), $depth);
                    if ($condition->kind === \Deriver\Deriver\Evaluator\TermKind::Choice && $condition->operands === []) {
                        return $condition;
                    }
                }
                $value = (new self($this->engine, $this->truth, $wanted, $finite))->search($source, $address, $parent, count($frame->graph->body->blocks[$parent]->instructions), $depth, $seen);
                return $guarded ? $guards->at($source, $parent, $guards->edge($source, $parent, $block, $value, $depth), $depth) : $value;
            };
        }
        if ($this->truth) {
            return (new \Deriver\Deriver\Evaluator\Predicate\Projection($this->engine->context->provenance, $this->finite))->collect($branches, $this->wanted);
        }
        $values = $this->engine->context->search->all($branches);
        $alternatives = array_map(static fn (Term $value, \Deriver\Deriver\Evaluator\Scope\Frame $source, int $parent): array => [$value, $source, $parent], $values, $sources, $parents);
        if ($guarded) {
            return (new \Deriver\Deriver\Evaluator\Enumeration\Choices($this->engine->context->provenance))->make(array_map(static fn (Term $value): array => [$value, []], $values));
        }
        return $this->merge($alternatives, $block, $depth);
    }

    /**

     * @param array<string, true> $seen

     */
    public function write(\Deriver\Deriver\Evaluator\Scope\Frame $frame, string $address, Instruction $write, int $block, int $offset, int $depth, array $seen): ?Term
    {
        $target = $write->operands[0] ?? '';
        if (in_array($write->operation, ['global', 'static-local'], true) && $this->key($frame, $target) === $this->key($frame, $address)) {
            return $this->declaration($frame, $write, $target, $depth);
        }
        if ($write->operation === 'alias') {
            if ($this->key($frame, $target) === $this->key($frame, $address)) {
                return $this->search($frame, $write->operands[1], $block, $offset, $depth, $seen);
            }
        }
        if (in_array($write->operation, ['invoke', 'invoke-method', 'invoke-static'], true)) {
            return (new \Deriver\Deriver\Evaluator\Calls($this->engine))->effect($frame, $write, $address, $depth);
        }
        if (!BodyIndex::writes($write)) {
            return null;
        }
        if ($write->operation === 'unset' && ($frame->graph->definitions[$target]->operation ?? '') === 'local') {
            return $this->key($frame, $target) === $this->key($frame, $address) ? Term::constant(null) : null;
        }
        $aliases = new \Deriver\Deriver\Evaluator\Memory\Aliases();
        $targetKey = $aliases->key($this, $frame, $target, $block, $offset);
        $wantedKey = $aliases->key($this, $frame, $address, $block, $offset);
        if (str_starts_with($targetKey, 'dynamic:') || str_starts_with($targetKey, 'unresolved-alias:') || str_starts_with($wantedKey, 'unresolved-alias:')) {
            return new Term(\Deriver\Deriver\Evaluator\TermKind::Write, $targetKey, [$this->search($frame, $address, $block, $offset, $depth, $seen), $this->engine->value($frame, $write->operands[1] ?? '', $depth)], ['reason' => 'UNRESOLVED_ALIAS', 'source' => $write->source->path, 'start' => $write->source->start]);
        }
        if ($targetKey !== $wantedKey) {
            return $this->elementWrite($frame, $address, $write, $block, $offset, $depth, $seen, isset($frame->graph->definitions[$targetKey]) ? $targetKey : null);
        }
        return \Deriver\Deriver\Evaluator\Memory\Mutations::apply($this->engine, $frame, $write, $address, $block, $offset, $depth, $seen, $this->truth, $this->wanted, $this->finite);
    }

    /**
     * Resolves a declared global or function-static storage origin.
     */
    public function declaration(\Deriver\Deriver\Evaluator\Scope\Frame $frame, Instruction $write, string $target, int $depth): Term
    {
        $name = $frame->graph->definitions[$target]->name;
        $identity = $write->operation === 'global' ? 'global:' . $name : 'static:' . $frame->graph->body->symbol . ':' . $name;
        $binding = $frame->bindings[$identity] ?? null;
        if ($binding instanceof \Deriver\Deriver\Evaluator\Scope\Binding) {
            return $binding->value($this->engine, 'mixed', $depth);
        }
        return $write->operation === 'global' ? ($this->engine->context->configuration->environment['$' . $name] ?? (new \Deriver\Deriver\Evaluator\Memory\Globals())->origin($this->engine, $frame, $write, $name, $depth)) : $this->engine->value($frame, $write->operands[1], $depth);
    }

    /**
     * Retains a demanded array update while leaving unrelated writes unexpanded.
     * @param array<string, true> $seen Visited definitions
     */
    public function elementWrite(\Deriver\Deriver\Evaluator\Scope\Frame $frame, string $address, Instruction $write, int $block, int $offset, int $depth, array $seen, ?string $aliasedAddress = null): ?Term
    {
        $target = $frame->graph->definitions[$aliasedAddress ?? $write->operands[0]] ?? null;
        if ($target?->operation !== 'element-address') {
            return null;
        }
        $path = [];
        $root = $target;
        while ($root->operation === 'element-address') {
            array_unshift($path, $root->operands[1]);
            $root = $frame->graph->definitions[$root->operands[0]];
        }
        if ($this->key($frame, $root->result) !== $this->key($frame, $address)) {
            return null;
        }
        $before = $this->search($frame, $address, $block, $offset, $depth, $seen);
        if ($path === []) {
            return null;
        }
        $keys = array_map(fn (string $register): Term => $register === '' ? new Term(\Deriver\Deriver\Evaluator\TermKind::Append) : $this->engine->value($frame, $register, $depth), $path);
        return (new \Deriver\Deriver\Evaluator\Memory\Elements($this->engine))->mutate($before, $keys, $write, $this->engine->value($frame, $write->operands[1] ?? '', $depth));
    }

    /**
     * Identifies storage without merging unrelated declarations or receivers.
     */
    public function key(\Deriver\Deriver\Evaluator\Scope\Frame $frame, string $address): string
    {
        $instruction = $frame->graph->definitions[$address] ?? null;
        if ($instruction === null) {
            return $address;
        }
        if ($instruction->operation === 'local') {
            return 'local:' . $instruction->name;
        }
        if ($instruction->operation === 'dynamic-local') {
            $name = $this->engine->value($frame, $instruction->operands[0], $this->engine->context->budget->maxDepth);
            return $name->kind === \Deriver\Deriver\Evaluator\TermKind::Constant && is_string($name->literal) ? 'local:' . $name->literal : 'dynamic:' . $instruction->result;
        }
        if ($instruction->operation === 'evaluator-state-address') {
            $receiver = $frame->graph->definitions[$instruction->operands[0]] ?? null;
            return 'state:' . $instruction->name . ':' . ($receiver?->operation === 'read' ? $this->key($frame, $receiver->operands[0]) : $instruction->operands[0]);
        }
        if ($instruction->operation === 'field-address') {
            $receiver = $this->engine->value($frame, $instruction->operands[0], $this->engine->context->budget->maxDepth);
            $name = $this->engine->context->index->literal($frame->graph, $instruction->operands[1]);
            if ($receiver->kind === \Deriver\Deriver\Evaluator\TermKind::Object && $name !== '') {
                return 'object:' . $receiver->literal . ':$' . $name;
            }
        }
        $property = $this->engine->context->index->declaredProperty($frame->graph, $instruction);
        if ($property !== null) {
            if ($instruction->operation === 'static-address') {
                return $property->className . '::$' . $property->name;
            }
            $receiver = $frame->graph->definitions[$instruction->operands[0]] ?? null;
            $local = $frame->graph->definitions[$receiver?->operands[0] ?? ''] ?? null;
            return $property->className . '::$' . $property->name . ':' . ($local?->operation === 'local' ? $local->name : $instruction->operands[0]);
        }
        return $address;
    }

    /**
     * Reads type information corresponding to the indexed definition.
     */
    public function type(\Deriver\Deriver\Evaluator\Scope\Frame $frame, Instruction $address): string
    {
        if ($address->operation === 'local') {
            foreach ($frame->graph->definitions as $instruction) {
                if ($instruction->operation === 'write' && $instruction->source->start < $address->source->start && ($frame->graph->definitions[$instruction->operands[0]]->name ?? '') === $address->name) {
                    return 'mixed';
                }
            }
            foreach ($frame->graph->body->parameters as $parameter) {
                if ($parameter->name === $address->name) {
                    return $parameter->type;
                }
            }
            return $address->name === 'this' ? $frame->graph->body->className : 'mixed';
        }
        return $this->engine->context->index->declaredProperty($frame->graph, $address)->type ?? 'mixed';
    }

    /**
     * Resolves the origin at the boundary of the local definition graph.
     */
    public function initial(\Deriver\Deriver\Evaluator\Scope\Frame $frame, string $address, int $depth): Term
    {
        $definition = $frame->graph->definitions[$address] ?? null;
        if ($definition?->operation === 'returned-address') {
            return $this->engine->value($frame, $definition->operands[0], $depth);
        }
        if ($definition?->operation === 'evaluator-state-address') {
            return (new \Deriver\Deriver\Evaluator\Memory\Slots($this->engine))->read($frame, $definition, $depth);
        }
        if ($definition?->operation === 'local') {
            foreach ($frame->graph->definitions as $instruction) {
                if ($instruction->operation === 'static-initialized' && $frame->graph->definitions[$instruction->operands[0]]->name === $definition->name) {
                    return (new \Deriver\Deriver\Evaluator\Memory\Statics())->incoming($this->engine, $frame, $definition, $depth);
                }
            }
            return (new Origins($this->engine, $this->truth, $this->wanted, $this->finite))->parameter($frame, $definition, $depth);
        }
        if ($definition?->operation === 'dynamic-local') {
            $key = $this->key($frame, $address);
            foreach ($frame->graph->definitions as $local) {
                if ($local->operation === 'local' && 'local:' . $local->name === $key) {
                    return (new Origins($this->engine, $this->truth, $this->wanted, $this->finite))->parameter($frame, $local, $depth);
                }
            }
        }
        if ($definition !== null && in_array($definition->operation, ['field-address', 'static-address'], true)) {
            return (new Origins($this->engine))->property($frame, $definition, $depth);
        }
        return $this->engine->context->reference($frame, $address, $definition->source ?? $frame->graph->body->source, reason: 'UNRESOLVED_STORAGE');
    }
    /**
     * Recognizes branch-local assignments so selecting conditions can be settled first.
     * @param list<int> $parents Predecessor blocks
     */
    public function branchesWriteLocal(\Deriver\Deriver\Evaluator\Scope\Frame $frame, string $address, array $parents): bool
    {
        $local = $frame->graph->definitions[$address] ?? null;
        if ($local?->operation !== 'local') {
            return false;
        }
        foreach ($parents as $parent) {
            $writes = false;
            foreach ($frame->graph->body->blocks[$parent]->instructions as $instruction) {
                $target = $frame->graph->definitions[$instruction->operands[0] ?? ''] ?? null;
                $writes = $writes || $instruction->operation === 'write' && $target?->operation === 'local' && $target->name === $local->name;
            }
            if (!$writes) {
                return false;
            }
        }
        return true;
    }

    /**
     * Shares an identical reaching value or retains its predecessor selections.
     * @param list<array{Term, \Deriver\Deriver\Evaluator\Scope\Frame, int}> $alternatives
     */
    public function merge(array $alternatives, int $block, int $depth): Term
    {
        $first = $alternatives[0][0];
        if (count(array_filter($alternatives, static fn (array $row): bool => $row[0] !== $first)) === 0) {
            return $first;
        }
        return (new \Deriver\Deriver\Evaluator\Enumeration\Choices($this->engine->context->provenance))->make(array_map(fn (array $row): array => [(new Guards($this->engine))->at($row[1], $row[2], (new Guards($this->engine))->edge($row[1], $row[2], $block, $row[0], $depth), $depth), []], $alternatives));
    }



}
