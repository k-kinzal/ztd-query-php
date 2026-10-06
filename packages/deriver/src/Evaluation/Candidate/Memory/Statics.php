<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Candidate\Memory;

use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Candidate\Calls;
use Deriver\Evaluation\Candidate\Choices;
use Deriver\Evaluation\Candidate\Derivation;
use Deriver\Evaluation\Candidate\Frame;
use Deriver\Evaluation\Candidate\Guards;
use Deriver\Value\Term;

/**
 * Resolves static storage from a supplied initial value or a known call prefix.
 * @visibility root
 */
final class Statics
{
    /**
     * Keeps unknown history distinct from the first call in a selected source sequence.
     */
    public function incoming(Derivation $engine, Frame $frame, Instruction $local, int $depth): Term
    {
        $name = 'static:' . $frame->graph->body->symbol . ':' . $local->name;
        $initial = $engine->context->configuration->environment[$name] ?? null;
        if ($initial !== null) {
            return $initial;
        }
        $origin = $frame->origin;
        $position = $origin?->frame->graph->positions[$origin->register] ?? null;
        if ($origin === null || $position === null) {
            return $engine->context->reference($frame, $name, $local->source, reason: 'UNKNOWN_STATIC_HISTORY');
        }
        return $this->previous($engine, $frame, $origin->frame, $local, $position[0], $position[1], $depth, []);
    }

    /**
     * Searches only earlier calls that can supply this static slot.
     * @param array<int, true> $seen Already searched predecessor blocks
     */
    public function previous(Derivation $engine, Frame $frame, Frame $caller, Instruction $local, int $block, int $offset, int $depth, array $seen): Term
    {
        if ($depth <= 0 || isset($seen[$block]) || $engine->context->work() !== null) {
            return $engine->context->reference($frame, 'static:' . $local->name, $local->source, reason: $engine->context->stopReason ?? ($depth <= 0 ? 'DEPTH_LIMIT' : 'UNKNOWN_STATIC_HISTORY'));
        }
        $seen[$block] = true;
        $instructions = $caller->graph->body->blocks[$block]->instructions;
        for ($index = $offset - 1; $index >= 0; $index--) {
            $call = $instructions[$index];
            if (strtolower($engine->context->index->target($caller->graph, $call)) !== strtolower($frame->graph->body->symbol)) {
                continue;
            }
            $bound = (new Calls($engine))->bind($caller, $call, $frame->graph, depth: $depth - 1);
            return (new Calls($engine))->finalStorage($bound, $local->result, $depth - 1);
        }
        $parents = $caller->graph->predecessors[$block] ?? [];
        if ($parents === []) {
            return new Term('static-uninitialized');
        }
        $values = [];
        foreach ($parents as $parent) {
            $value = $this->previous($engine, $frame, $caller, $local, $parent, count($caller->graph->body->blocks[$parent]->instructions), $depth, $seen);
            $values[] = [(new Guards($engine))->at($caller, $parent, $value, $depth), []];
        }
        return (new Choices())->make($values);
    }

    /**
     * Tests whether the selected call prefix supplies an initialized slot.
     */
    public function initialized(Derivation $engine, Frame $frame, Instruction $local, int $depth): Term
    {
        return (new Choices())->apply('static-initialized', [$this->incoming($engine, $frame, $local, $depth)], static fn (array $values): Term => $values[0]->kind === 'static-uninitialized' ? Term::constant(false) : (($values[0]->attributes['reason'] ?? '') === 'UNKNOWN_STATIC_HISTORY' ? new Term('operation', 'static-initialized', $values) : Term::constant(true)), $engine->context->budget->partitions);
    }
}
