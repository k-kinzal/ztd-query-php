<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Memory;

use Deriver\Deriver\Evaluator\Calls;
use Deriver\Deriver\Evaluator\Enumeration\Choices;
use Deriver\Deriver\Evaluator\Evaluator;
use Deriver\Deriver\Evaluator\Expression\Guards;
use Deriver\Deriver\Evaluator\Scope\Frame;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Lowerer\Graph\Instruction;

/**
 * Resolves static storage from its source initializer and known call prefix.
 * @visibility parent
 */
final class Statics
{
    /**
     * Keeps unknown history distinct from the first call in a selected source sequence.
     */
    public function incoming(Evaluator $engine, Frame $frame, Instruction $local, int $depth): Term
    {
        $origin = $frame->origin;
        $position = $origin?->frame->graph->positions[$origin->register] ?? null;
        if ($origin === null || $position === null) {
            return $engine->context->reference($frame, 'static:' . $frame->graph->body->symbol . ':' . $local->name, $local->source, reason: 'UNKNOWN_STATIC_HISTORY');
        }
        return $this->previous($engine, $frame, $origin->frame, $local, $position[0], $position[1], $depth, []);
    }

    /**
     * Searches only earlier calls that can supply this static slot.
     * @param array<int, true> $seen Already searched predecessor blocks
     */
    public function previous(Evaluator $engine, Frame $frame, Frame $caller, Instruction $local, int $block, int $offset, int $depth, array $seen): Term
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
            return new Term(\Deriver\Deriver\Evaluator\TermKind::StaticUninitialized);
        }
        $values = [];
        foreach ($parents as $parent) {
            $value = $this->previous($engine, $frame, $caller, $local, $parent, count($caller->graph->body->blocks[$parent]->instructions), $depth, $seen);
            $values[] = [(new Guards($engine))->at($caller, $parent, $value, $depth), []];
        }
        return (new Choices($engine->context->provenance))->make($values);
    }

    /**
     * Tests whether the selected call prefix supplies an initialized slot.
     */
    public function initialized(Evaluator $engine, Frame $frame, Instruction $local, int $depth): Term
    {
        return (new Choices($engine->context->provenance))->apply('static-initialized', [$this->incoming($engine, $frame, $local, $depth)], static fn (array $values): Term => $values[0]->kind === \Deriver\Deriver\Evaluator\TermKind::StaticUninitialized ? Term::constant(false) : (($values[0]->attributes['reason'] ?? '') === 'UNKNOWN_STATIC_HISTORY' ? new Term(\Deriver\Deriver\Evaluator\TermKind::Operation, 'static-initialized', $values) : Term::constant(true)), $engine->context->budget->partitions);
    }
}
