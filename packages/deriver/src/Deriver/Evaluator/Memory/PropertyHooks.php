<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Memory;

use Deriver\Deriver\Evaluator\Calls;
use Deriver\Deriver\Evaluator\Evaluator;
use Deriver\Deriver\Evaluator\Expression\Storage;
use Deriver\Deriver\Evaluator\Index\BodyIndex;
use Deriver\Deriver\Evaluator\Scope\Binding;
use Deriver\Deriver\Evaluator\Scope\Frame;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Lowerer\Declaration\Metadata\PropertyDeclaration;
use Deriver\Deriver\Lowerer\Graph\Instruction;

/**
 * Expands demanded property accessors while preserving access to their backing slot.
 * @visibility parent
 */
final class PropertyHooks
{
    /**
     * Finds an accessor on a resolved receiver, excluding its own backing access.
     * @return array{Term, PropertyDeclaration}|null
     */
    public function resolve(Evaluator $engine, Frame $frame, Instruction $address, int $depth): ?array
    {
        if ($address->operation !== 'field-address') {
            return null;
        }
        $receiver = $engine->value($frame, $address->operands[0], $depth);
        $name = $engine->context->index->literal($frame->graph, $address->operands[1]);
        $property = $engine->context->index->property((string) ($receiver->attributes['class'] ?? ''), $name);
        if ($property === null || in_array($frame->graph->body->symbol, [$property->get?->symbol, $property->set?->symbol], true)) {
            return null;
        }
        return [$receiver, $property];
    }

    /**
     * Derives the getter result at the requested read position.
     */
    public function read(Evaluator $engine, Frame $frame, Instruction $read, Instruction $address, int $depth): ?Term
    {
        $resolved = $this->resolve($engine, $frame, $address, $depth);
        if ($resolved === null) {
            return null;
        }
        [$receiver, $property] = $resolved;
        $body = $property->get;
        if ($body === null) {
            return null;
        }
        $bound = new Frame(new BodyIndex($body), $frame->identity . ':get:' . $read->result, ['this' => $receiver], invocation: true, origin: new Binding($frame, $read->result), calledClass: (string) ($receiver->attributes['class'] ?? ''));
        return $engine->returns($bound, $depth);
    }

    /**
     * Derives the backing slot after a setter, with the preceding value available lazily.
     */
    public function write(Evaluator $engine, Frame $frame, Instruction $write, string $address, int $block, int $offset, int $depth): ?Term
    {
        $definition = $frame->graph->definitions[$address] ?? null;
        $resolved = $definition === null ? null : $this->resolve($engine, $frame, $definition, $depth);
        if ($resolved === null || $write->operation !== 'write') {
            return null;
        }
        [$receiver, $property] = $resolved;
        $body = $property->set;
        if ($body === null) {
            return null;
        }
        $before = (new Storage($engine))->search($frame, $address, $block, $offset, $depth);
        $bindings = ['this' => $receiver, $body->parameters[0]->name => new Binding($frame, $write->operands[1])];
        $bound = new Frame(new BodyIndex($body), $frame->identity . ':set:' . $write->result, $bindings, [$property->name => $before], invocation: true, origin: new Binding($frame, $write->result), calledClass: (string) ($receiver->attributes['class'] ?? ''));
        [$owner, $slot] = (new PropertyEffects())->address($bound, $receiver, $property->name);
        return (new Calls($engine))->finalStorage($owner, $slot, $depth);
    }
}
