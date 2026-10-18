<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Memory;

use Deriver\Deriver\Evaluator\Calls;
use Deriver\Deriver\Evaluator\Evaluator;
use Deriver\Deriver\Evaluator\Expression\Storage;
use Deriver\Deriver\Evaluator\Index\BodyIndex;
use Deriver\Deriver\Evaluator\Invocation\Bodies;
use Deriver\Deriver\Evaluator\Invocation\Dispatch;
use Deriver\Deriver\Evaluator\Scope\Frame;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Lowerer\Graph\Instruction;

/**
 * Follows a demanded instance field through the same call and storage machinery.
 * @visibility parent
 */
final class PropertyEffects
{
    /**
     * Adds an observation address to an isolated index without altering captured source.
     * @return array{Frame, string} Observation frame and synthetic field address
     */
    public function address(Frame $owner, Term $receiver, string $name): array
    {
        $graph = new BodyIndex($owner->graph->body);
        $key = 'property-observation:' . hash('sha256', (string) $receiver->literal . ':' . $name);
        $source = $owner->graph->body->source;
        $graph->definitions[$key . ':receiver'] = new \Deriver\Deriver\Evaluator\Call\BoundInstruction($key . ':receiver', 'constant', $source, $key . ':receiver', constant: $receiver);
        $graph->definitions[$key . ':name'] = new \Deriver\Deriver\Evaluator\Call\BoundInstruction($key . ':name', 'constant', $source, $key . ':name', constant: Term::constant($name));
        $graph->definitions[$key] = new \Deriver\Deriver\Evaluator\Call\BoundInstruction($key, 'field-address', $source, $key, [$key . ':receiver', $key . ':name']);
        return [new Frame($graph, $owner->identity, $owner->bindings, $owner->properties, $owner->calls, $owner->invocation, $owner->iterations, origin: $owner->origin, calledClass: $owner->calledClass), $key];
    }

    /**
     * Observes the same allocation immediately before its method invocation.
     */
    public function incoming(Evaluator $engine, Frame $frame, Term $receiver, string $name, int $depth): ?Term
    {
        $origin = $frame->origin;
        $position = $origin?->frame->graph->positions[$origin->register] ?? null;
        if ($origin === null || $position === null || $receiver->kind !== \Deriver\Deriver\Evaluator\TermKind::Object) {
            return null;
        }
        [$owner, $address] = $this->address($origin->frame, $receiver, $name);
        return (new Storage($engine))->search($owner, $address, $position[0], $position[1], $depth);
    }

    /**
     * Selects a call that can write the observed allocation's field.
     */
    public function effect(Evaluator $engine, Frame $caller, Instruction $call, string $address, int $depth): ?Term
    {
        $field = $caller->graph->definitions[$address] ?? null;
        if ($field?->operation !== 'field-address' || !in_array($call->operation, ['invoke-method', 'invoke-static'], true)) {
            return null;
        }
        $receiver = $engine->value($caller, $field->operands[0], $depth);
        if ($receiver->kind !== \Deriver\Deriver\Evaluator\TermKind::Object) {
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
    public function selected(Evaluator $engine, Frame $caller, Instruction $call, string $target, Term $receiver, string $name, Term $before, int $depth): Term
    {
        $reason = $engine->context->boundary($depth);
        if ($reason !== null || ($caller->calls[$call->id] ?? 0) >= $engine->context->budget->recursion) {
            return new Term(\Deriver\Deriver\Evaluator\TermKind::PropertyWrite, $name, [$before], ['reason' => $reason ?? 'RECURSION_LIMIT']);
        }
        $body = (new Bodies($engine))->select($caller, $call, $target, $depth - 1);
        $graph = $body->implementation;
        if ($graph instanceof Term) {
            return $body->replacement ? $before : new Term(\Deriver\Deriver\Evaluator\TermKind::PropertyWrite, $name, [$before, $graph]);
        }
        if ($graph === null || $graph->body->external) {
            return new Term(\Deriver\Deriver\Evaluator\TermKind::PropertyWrite, $name, [$before], ['reason' => 'MISSING_SOURCE']);
        }
        $property = $engine->context->index->property((string) ($receiver->attributes['type'] ?? ''), $name);
        if (!$body->replacement && $property?->readonly === true && !str_ends_with(strtolower($target), '::__construct') && !str_ends_with(strtolower($target), '::__clone')) {
            $initializers = $engine->context->index->writes($property);
            if ($initializers !== [] && array_filter($initializers, static fn (array $site): bool => !str_ends_with(strtolower($site[0]->body->symbol), '::__construct')) === []) {
                return $before;
            }
        }
        $bound = (new Calls($engine))->bind($caller, $call, $graph, $receiver, $depth - 1);
        $bound = new Frame($graph, $bound->identity . ':property:' . $name, $bound->bindings, [$name => $before], $bound->calls, true, origin: $bound->origin, calledClass: $bound->calledClass);
        [$observation, $address] = $this->address($bound, $receiver, $name);
        $body->enter($engine->context);
        return (new Calls($engine))->finalStorage($observation, $address, $depth - 1);
    }
}
