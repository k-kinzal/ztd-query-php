<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Candidate\Memory;

use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Candidate\Binding;
use Deriver\Evaluation\Candidate\Calls;
use Deriver\Evaluation\Candidate\Derivation;
use Deriver\Evaluation\Candidate\Frame;
use Deriver\Evaluation\Candidate\Invocation\Bodies;
use Deriver\Value\Term;

/**
 * Follows source calls which write a demanded global, with the caller's prior binding.
 * @visibility root
 */
final class Globals
{
    /**
     * Follows a call write only when it can affect the requested global.
     */
    public function effect(Derivation $engine, Frame $caller, Instruction $call, string $address, int $depth): ?Term
    {
        $name = $caller->graph->definitions[$address]->name ?? '';
        if ($name === '' || !$this->declares($caller, $name)) {
            return null;
        }
        $target = $engine->context->index->target($caller->graph, $call);
        $body = (new Bodies($engine))->select($caller, $call, $target, $depth);
        $graph = $body->implementation;
        if ($graph === null) {
            $position = $caller->graph->positions[$call->result];
            $before = (new \Deriver\Evaluation\Candidate\Storage($engine))->search($caller, $address, $position[0], $position[1], $depth);
            return new Term('global-write', $target, [$before], ['reason' => 'MISSING_SOURCE', 'type' => 'mixed']);
        }
        if ($graph instanceof Term) {
            return null;
        }
        $bound = (new Calls($engine))->bind($caller, $call, $graph);
        if (!$this->declares($bound, $name)) {
            return null;
        }
        $bindings = $bound->bindings;
        $bindings['global:' . $name] = new Binding($caller, $address, position: $caller->graph->positions[$call->result]);
        $bound = new Frame($graph, $bound->identity, $bindings, $bound->properties, $bound->calls, true, origin: $bound->origin, calledClass: $bound->calledClass);
        foreach ($graph->definitions as $write) {
            $local = Mutations::root($graph, $write);
            if (Mutations::writes($write) && $local?->operation === 'local' && $local->name === $name) {
                $body->enter($engine->context);
                return (new Calls($engine))->finalStorage($bound, $local->result, $depth);
            }
        }
        return null;
    }

    /**
     * Follows the caller's prior global or captured script assignments.
     */
    public function origin(Derivation $engine, Frame $frame, Instruction $write, string $name, int $depth): Term
    {
        $origin = $frame->origin;
        if ($origin !== null && $this->declares($origin->frame, $name)) {
            foreach ($origin->frame->graph->definitions as $local) {
                if ($local->operation === 'local' && $local->name === $name) {
                    $position = $origin->frame->graph->positions[$origin->register] ?? null;
                    if ($position !== null) {
                        return (new Binding($origin->frame, $local->result, position: $position))->value($engine, 'mixed', $depth);
                    }
                }
            }
        }
        $key = $frame->identity . ':global-origin:' . $name;
        if (isset($engine->context->active[$key])) {
            return $engine->context->reference($frame, 'global:' . $name, $write->source, reason: 'CYCLE');
        }
        $engine->context->active[$key] = true;
        $values = [];
        foreach ($engine->context->index->program->symbols() as $symbol) {
            if (!str_starts_with($symbol, 'script:')) {
                continue;
            }
            $graph = $engine->context->index->graph($symbol);
            if ($graph === null) {
                continue;
            }
            $owner = new Frame($graph, 'source:' . $symbol);
            foreach ($graph->definitions as $definition) {
                $local = Mutations::root($graph, $definition);
                if (!Mutations::writes($definition) || $local?->operation !== 'local' || $local->name !== $name) {
                    continue;
                }
                [$block, $offset] = $graph->positions[$definition->result];
                $value = (new \Deriver\Evaluation\Candidate\Storage($engine))->search($owner, $local->result, $block, $offset + 1, $depth);
                $values[] = [(new \Deriver\Evaluation\Candidate\Guards($engine))->at($owner, $block, $value, $depth), []];
            }
        }
        unset($engine->context->active[$key]);
        return $values === [] ? $engine->context->reference($frame, 'global:' . $name, $write->source) : (new \Deriver\Evaluation\Candidate\Choices())->make($values);
    }

    /**
     * Checks whether this local denotes global storage.
     */
    public function declares(Frame $frame, string $name): bool
    {
        if (str_starts_with($frame->graph->body->symbol, 'script:')) {
            return true;
        }
        foreach ($frame->graph->definitions as $definition) {
            if ($definition->operation === 'global' && ($frame->graph->definitions[$definition->operands[0]]->name ?? '') === $name) {
                return true;
            }
        }
        return false;
    }
}
