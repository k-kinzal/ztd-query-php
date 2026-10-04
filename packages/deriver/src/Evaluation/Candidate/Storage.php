<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Candidate;

use Deriver\ControlFlow\Instruction;
use Deriver\Value\Term;

/**
 * Follows reaching writes backwards; unrelated instructions are only inspected as index entries.
 * @visibility root
 */
final class Storage
{
    /**
     * Captures the dependencies used by this component.
     */
    public function __construct(public readonly Derivation $engine)
    {
    }

    /**
     * Follows the reaching definition for the requested read.
     */
    public function read(Frame $frame, Instruction $read, string $address, int $depth): Term
    {
        $definition = $frame->graph->definitions[$address] ?? $read;
        $name = $definition->operation === 'local' ? '$' . $definition->name : $definition->operation . ':' . $address;
        $context = $this->engine->context;
        $type = $this->type($frame, $definition);
        $reason = $context->boundary($depth);
        if ($reason !== null) {
            return $context->reference($frame, $name, $read->source, $type, $reason, 'deferred');
        }
        $context->referenceExpansions++;
        $context->references[$frame->graph->body->symbol . ':' . $name] = ($context->references[$frame->graph->body->symbol . ':' . $name] ?? 0) + 1;
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
    public function modified(Frame $frame, string $name): bool
    {
        foreach ($frame->graph->definitions as $instruction) {
            $address = $frame->graph->definitions[$instruction->operands[0] ?? ''] ?? null;
            if ($instruction->operation === 'alias' || in_array($instruction->operation, ['write', 'increment', 'compound', 'unset', 'global', 'static-local'], true) && ($address?->operation === 'element-address' || $address?->operation === 'local' && $address->name === $name)) {
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
                if ($body === null || ($body->parameters[$position]->byReference ?? true)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**

     * @param array<string, true> $seen

     */
    public function search(Frame $frame, string $address, int $block, int $offset, int $depth, array $seen = []): Term
    {
        $key = $frame->identity . ':' . $this->key($frame, $address) . ':' . $block . ':' . $offset;
        if (isset($seen[$key])) {
            return $this->engine->context->reference($frame, $this->key($frame, $address), $frame->graph->body->source, reason: 'CYCLE', kind: 'recursive');
        }
        $seen[$key] = true;
        $instructions = $frame->graph->body->blocks[$block]->instructions;
        for ($index = $offset - 1; $index >= 0; $index--) {
            $write = $instructions[$index];
            $value = $this->write($frame, $address, $write, $block, $index, $depth, $seen);
            if ($value !== null) {
                return ($frame->graph->positions[$address][0] ?? null) === $block ? $value : (new Guards($this->engine))->at($frame, $block, $value, $depth);
            }
        }
        $parents = (new Recurrence\Definitions($this->engine))->parents($frame, $block);
        if ($frame->graph->body->blocks[$block]->loopHeader) {
            return (new Recurrence\Definitions($this->engine))->value($frame, $address, $block, $depth);
        }
        if ($parents === []) {
            return $this->initial($frame, $address, $depth);
        }
        $alternatives = [];
        foreach ($parents as $parent) {
            $source = (new Recurrence\Definitions($this->engine))->predecessor($frame, $parent, $block);
            $value = $this->search($source, $address, $parent, count($frame->graph->body->blocks[$parent]->instructions), $depth, $seen);
            $value = (new Guards($this->engine))->edge($source, $parent, $block, $value, $depth);
            $alternatives[] = [$value, []];
        }
        return (new Choices())->make($alternatives);
    }

    /**

     * @param array<string, true> $seen

     */
    public function write(Frame $frame, string $address, Instruction $write, int $block, int $offset, int $depth, array $seen): ?Term
    {
        $target = $write->operands[0] ?? '';
        if (in_array($write->operation, ['global', 'static-local'], true) && $this->key($frame, $target) === $this->key($frame, $address)) {
            $name = $frame->graph->definitions[$target]->name;
            $identity = $write->operation === 'global' ? 'global:' . $name : 'static:' . $frame->graph->body->symbol . ':' . $name;
            return $this->engine->context->configuration->environment[$identity] ?? ($write->operation === 'global' ? $this->engine->context->reference($frame, $identity, $write->source) : $this->engine->value($frame, $write->operands[1], $depth));
        }
        if ($write->operation === 'alias') {
            if ($this->key($frame, $target) === $this->key($frame, $address)) {
                return $this->search($frame, $write->operands[1], $block, $offset, $depth, $seen);
            }
        }
        if (in_array($write->operation, ['invoke', 'invoke-method', 'invoke-static'], true)) {
            return (new Calls($this->engine))->effect($frame, $write, $address, $depth);
        }
        if (!in_array($write->operation, ['write', 'compound', 'increment', 'unset'], true)) {
            return null;
        }
        $aliases = new Memory\Aliases();
        $targetKey = $aliases->key($this, $frame, $target, $block, $offset);
        $wantedKey = $aliases->key($this, $frame, $address, $block, $offset);
        if (str_starts_with($targetKey, 'unresolved-alias:') || str_starts_with($wantedKey, 'unresolved-alias:')) {
            return new Term('write', $targetKey, [$this->search($frame, $address, $block, $offset, $depth, $seen), $this->engine->value($frame, $write->operands[1] ?? '', $depth)], ['reason' => 'UNRESOLVED_ALIAS', 'source' => $write->source->path, 'start' => $write->source->start]);
        }
        if ($targetKey !== $wantedKey) {
            return $this->elementWrite($frame, $address, $write, $block, $offset, $depth, $seen);
        }
        $this->engine->context->record($frame, $write);
        if ($write->operation === 'write') {
            return $this->engine->value($frame, $write->operands[1], $depth);
        }
        if ($write->operation === 'unset') {
            return Term::constant(null);
        }
        $before = $this->search($frame, $address, $block, $offset, $depth, $seen);
        $right = $write->operation === 'increment' ? Term::constant((int) $write->attributes['delta']) : $this->engine->value($frame, $write->operands[1], $depth);
        return $this->engine->operation('binary', $write->operation === 'increment' ? '+' : $write->name, [$before, $right]);
    }

    /**
     * Retains a demanded array update while leaving unrelated writes unexpanded.
     * @param array<string, true> $seen Visited definitions
     */
    public function elementWrite(Frame $frame, string $address, Instruction $write, int $block, int $offset, int $depth, array $seen): ?Term
    {
        $target = $frame->graph->definitions[$write->operands[0]] ?? null;
        if ($target?->operation !== 'element-address' || $this->key($frame, $target->operands[0]) !== $this->key($frame, $address)) {
            return null;
        }
        $before = $this->search($frame, $address, $block, $offset, $depth, $seen);
        $key = $target->operands[1] === '' ? new Term('append') : $this->engine->value($frame, $target->operands[1], $depth);
        $right = $this->engine->value($frame, $write->operands[1] ?? '', $depth);
        if ($write->operation === 'compound' || $write->operation === 'increment') {
            $previous = $this->engine->element($before, $key);
            $right = $this->engine->operation('binary', $write->operation === 'increment' ? '+' : $write->name, [$previous, $write->operation === 'increment' ? Term::constant((int) $write->attributes['delta']) : $right]);
        }
        if ($write->operation === 'unset' && $before->kind === 'array' && $key->kind === 'constant') {
            $entries = $before->operands;
            unset($entries[(string) $key->literal]);
            return new Term('array', operands: $entries, attributes: $before->attributes);
        }
        return $this->engine->operation('array-set', '', [$before, $key, $right]);
    }

    /**
     * Identifies storage without merging unrelated declarations or receivers.
     */
    public function key(Frame $frame, string $address): string
    {
        $instruction = $frame->graph->definitions[$address] ?? null;
        if ($instruction === null) {
            return $address;
        }
        if ($instruction->operation === 'local') {
            return 'local:' . $instruction->name;
        }
        if ($instruction->operation === 'model-state-address') {
            $receiver = $frame->graph->definitions[$instruction->operands[0]] ?? null;
            return 'state:' . $instruction->name . ':' . ($receiver?->operation === 'read' ? $this->key($frame, $receiver->operands[0]) : $instruction->operands[0]);
        }
        $property = $this->engine->context->index->declaredProperty($frame->graph, $instruction);
        if ($property !== null) {
            $receiver = $frame->graph->definitions[$instruction->operands[0]] ?? null;
            $local = $frame->graph->definitions[$receiver?->operands[0] ?? ''] ?? null;
            return $property->className . '::$' . $property->name . ':' . ($local?->operation === 'local' ? $local->name : $instruction->operands[0]);
        }
        return $address;
    }

    /**
     * Reads type information corresponding to the indexed definition.
     */
    public function type(Frame $frame, Instruction $address): string
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
    public function initial(Frame $frame, string $address, int $depth): Term
    {
        $definition = $frame->graph->definitions[$address] ?? null;
        if ($definition?->operation === 'model-state-address') {
            return (new Memory\Slots($this->engine))->read($frame, $definition, $depth);
        }
        if ($definition?->operation === 'local') {
            return (new Origins($this->engine))->parameter($frame, $definition, $depth);
        }
        if ($definition !== null && in_array($definition->operation, ['field-address', 'static-address'], true)) {
            return (new Origins($this->engine))->property($frame, $definition, $depth);
        }
        return $this->engine->context->reference($frame, $address, $definition->source ?? $frame->graph->body->source, reason: 'UNRESOLVED_STORAGE');
    }
}
