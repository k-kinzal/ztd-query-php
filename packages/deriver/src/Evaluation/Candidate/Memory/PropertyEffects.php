<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Candidate\Memory;

use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Candidate\Calls;
use Deriver\Evaluation\Candidate\Derivation;
use Deriver\Evaluation\Candidate\Frame;
use Deriver\Evaluation\Candidate\Graph;
use Deriver\Evaluation\Candidate\Invocation\Bodies;
use Deriver\Evaluation\Candidate\Invocation\Dispatch;
use Deriver\Evaluation\Candidate\Storage;
use Deriver\Value\Term;

/**
 * Follows a demanded instance field through the same call and storage machinery.
 * @visibility root
 */
final class PropertyEffects
{
    /**
     * Adds an observation address to an isolated index without altering captured source.
     * @return array{Frame, string} Observation frame and synthetic field address
     */
    public function address(Frame $owner, Term $receiver, string $name): array
    {
        $graph = new Graph($owner->graph->body, $owner->graph->modelEvidence);
        $key = 'property-observation:' . hash('sha256', (string) $receiver->literal . ':' . $name);
        $source = $owner->graph->body->source;
        $graph->definitions[$key . ':receiver'] = new Instruction($key . ':receiver', 'constant', $source, $key . ':receiver', constant: $receiver);
        $graph->definitions[$key . ':name'] = new Instruction($key . ':name', 'constant', $source, $key . ':name', constant: Term::constant($name));
        $graph->definitions[$key] = new Instruction($key, 'field-address', $source, $key, [$key . ':receiver', $key . ':name']);
        return [new Frame($graph, $owner->identity, $owner->bindings, $owner->properties, $owner->calls, $owner->invocation, $owner->iterations, origin: $owner->origin, calledClass: $owner->calledClass), $key];
    }

    /**
     * Observes the same allocation immediately before its method invocation.
     */
    public function incoming(Derivation $engine, Frame $frame, Term $receiver, string $name, int $depth): ?Term
    {
        $origin = $frame->origin;
        $position = $origin?->frame->graph->positions[$origin->register] ?? null;
        if ($origin === null || $position === null || $receiver->kind !== 'object') {
            return null;
        }
        [$owner, $address] = $this->address($origin->frame, $receiver, $name);
        return (new Storage($engine))->search($owner, $address, $position[0], $position[1], $depth);
    }

    /**
     * Selects a call that can write the observed allocation's field.
     */
    public function effect(Derivation $engine, Frame $caller, Instruction $call, string $address, int $depth): ?Term
    {
        $field = $caller->graph->definitions[$address] ?? null;
        if ($field?->operation !== 'field-address' || !in_array($call->operation, ['invoke-method', 'invoke-static'], true)) {
            return null;
        }
        $receiver = $engine->value($caller, $field->operands[0], $depth);
        if ($receiver->kind !== 'object') {
            return null;
        }
        $name = $engine->context->index->literal($caller->graph, $field->operands[1]);
        return (new Dispatch($engine))->apply($caller, $call, $depth, function (string $target, ?Term $called) use ($engine, $caller, $call, $receiver, $name, $address, $depth): Term {
            [$block, $offset] = $caller->graph->positions[$call->result];
            $before = (new Storage($engine))->search($caller, $address, $block, $offset, $depth);
            if ($called?->literal !== $receiver->literal) {
                return $before;
            }
            return $this->selected($engine, $caller, $call, $target, $receiver, $name, $before, $depth);
        });
    }

    /**
     * Derives a selected implementation's final field from the caller's prior definition.
     */
    public function selected(Derivation $engine, Frame $caller, Instruction $call, string $target, Term $receiver, string $name, Term $before, int $depth): Term
    {
        $reason = $engine->context->boundary($depth);
        if ($reason !== null || ($caller->calls[$call->id] ?? 0) >= $engine->context->budget->recursion) {
            return new Term('property-write', $name, [$before], ['reason' => $reason ?? 'RECURSION_LIMIT']);
        }
        $body = (new Bodies($engine))->select($caller, $call, $target, $depth - 1);
        $graph = $body->implementation;
        if ($graph instanceof Term) {
            return $body->replacement ? $before : new Term('property-write', $name, [$before, $graph]);
        }
        if ($graph === null || $graph->body->external) {
            return new Term('property-write', $name, [$before], ['reason' => 'MISSING_SOURCE']);
        }
        $bound = (new Calls($engine))->bind($caller, $call, $graph, $receiver, $depth - 1);
        $bound = new Frame($graph, $bound->identity . ':property:' . $name, $bound->bindings, [$name => $before], $bound->calls, true, origin: $bound->origin, calledClass: $bound->calledClass);
        [$observation, $address] = $this->address($bound, $receiver, $name);
        $body->enter($engine->context);
        return (new Calls($engine))->finalStorage($observation, $address, $depth - 1);
    }
}
