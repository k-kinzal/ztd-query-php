<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Memory;

use Deriver\Deriver\Evaluator\Calls;
use Deriver\Deriver\Evaluator\Evaluator;
use Deriver\Deriver\Evaluator\Index\BodyIndex;
use Deriver\Deriver\Evaluator\Invocation\Bodies;
use Deriver\Deriver\Evaluator\Scope\Binding;
use Deriver\Deriver\Evaluator\Scope\Frame;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Lowerer\Graph\Instruction;

/**
 * Follows source calls which write a demanded global, with the caller's prior binding.
 * @visibility parent
 */
final class Globals
{
    /**
     * Follows a call write only when it can affect the requested global.
     */
    public function effect(Evaluator $engine, Frame $caller, Instruction $call, string $address, int $depth): ?Term
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
            $before = (new \Deriver\Deriver\Evaluator\Expression\Storage($engine))->search($caller, $address, $position[0], $position[1], $depth);
            return new Term(\Deriver\Deriver\Evaluator\TermKind::GlobalWrite, $target, [$before], ['reason' => 'MISSING_SOURCE', 'type' => 'mixed']);
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
            $local = $graph->root($write);
            if (BodyIndex::writes($write) && $local?->operation === 'local' && $local->name === $name) {
                $body->enter($engine->context);
                return (new Calls($engine))->finalStorage($bound, $local->result, $depth);
            }
        }
        return null;
    }

    /**
     * Follows the caller's prior global or captured script assignments.
     */
    public function origin(Evaluator $engine, Frame $frame, Instruction $write, string $name, int $depth): Term
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
        foreach ($engine->context->index->program->globalOwners($name) as $symbol) {
            $graph = $engine->context->index->graph($symbol);
            if ($graph === null) {
                continue;
            }
            $owner = new Frame($graph, 'source:' . $symbol);
            foreach ($graph->definitions as $definition) {
                $local = $graph->root($definition);
                if (!BodyIndex::writes($definition) || $local?->operation !== 'local' || $local->name !== $name) {
                    continue;
                }
                [$block, $offset] = $graph->positions[$definition->result];
                $value = (new \Deriver\Deriver\Evaluator\Expression\Storage($engine))->search($owner, $local->result, $block, $offset + 1, $depth);
                $values[] = [(new \Deriver\Deriver\Evaluator\Expression\Guards($engine))->at($owner, $block, $value, $depth), []];
            }
        }
        unset($engine->context->active[$key]);
        return $values === [] ? $engine->context->reference($frame, 'global:' . $name, $write->source) : (new \Deriver\Deriver\Evaluator\Enumeration\Choices())->make($values);
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
