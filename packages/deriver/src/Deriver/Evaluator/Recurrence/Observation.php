<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Recurrence;

use Deriver\Deriver\Evaluator\Evaluator;
use Deriver\Deriver\Evaluator\Scope\Frame;

/**
 * Uses the entry bindings inside a body proven to run at most once.
 * @visibility parent
 */
final class Observation
{
    /**
     * Shares loop bounds with storage and branch pruning.
     */
    public function __construct(private readonly Evaluator $engine)
    {
    }

    /**
     * Selects a body observation without unfolding subsequent iterations.
     */
    public function frame(Frame $frame, int $block, int $depth): Frame
    {
        if (!$this->engine->context->configuration->hasBranchEvaluators || isset($this->engine->context->evaluationEvidence[$frame->graph->body])) {
            return $frame;
        }
        foreach ($this->bodies($frame) as $header => $blocks) {
            if (!isset($blocks[$block]) || isset($frame->iterations[$header])) {
                continue;
            }
            $bounds = (new Bounds($this->engine))->of($frame, $header, $depth);
            if (($bounds->attributes['maximum'] ?? -1) === 1) {
                $frame = $frame->iteration($header, 0);
            }
        }
        return $frame;
    }

    /**
     * Selects entry or exit bindings for an edge constrained to zero or one pass.
     */
    public function condition(Frame $frame, int $header, bool $expected, int $depth): Frame
    {
        if (!$this->engine->context->configuration->hasBranchEvaluators || !$frame->graph->body->blocks[$header]->loopHeader || isset($frame->iterations[$header]) || isset($this->engine->context->evaluationEvidence[$frame->graph->body])) {
            return $frame;
        }
        $bounds = (new Bounds($this->engine))->of($frame, $header, $depth);
        $maximum = $bounds->attributes['maximum'] ?? -1;
        if ($maximum !== 0 && $maximum !== 1) {
            return $frame;
        }
        $version = !$expected && $maximum === 1 && !(new Definitions($this->engine))->postCondition($frame, $header) ? 1 : 0;
        return $frame->iteration($header, $version);
    }

    /**
     * @return array<int, array<int, true>> Indexed structural body membership
     */
    public function bodies(Frame $frame): array
    {
        if ($frame->graph->loopBodies !== null) {
            return $frame->graph->loopBodies;
        }
        $bodies = [];
        foreach ($frame->graph->body->blocks as $header => $block) {
            if (!$block->loopHeader) {
                continue;
            }
            $pending = [$block->terminator->targets[0]];
            $exit = $block->terminator->targets[1];
            $seen = [];
            while ($pending !== []) {
                $id = array_pop($pending);
                if ($id === $header || $id === $exit || isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                array_push($pending, ...($frame->graph->successors[$id] ?? []));
            }
            $bodies[$header] = $seen;
        }
        return $frame->graph->loopBodies = $bodies;
    }
}
