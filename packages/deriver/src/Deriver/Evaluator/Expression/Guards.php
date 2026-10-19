<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Expression;

use Deriver\Deriver\Evaluator\Enumeration\Choices;
use Deriver\Deriver\Evaluator\Scalar\Operations;
use Deriver\Deriver\Evaluator\Term;

/**
 * Retains source branch identity without opening predicate dependencies.
 * @visibility parent
 */
final class Guards
{
    /**
     * Captures the dependencies used by this component.
     */
    public function __construct(public readonly \Deriver\Deriver\Evaluator\Evaluator $engine)
    {
    }

    /**

     * @param array<int, true> $seen

     */
    public function at(\Deriver\Deriver\Evaluator\Scope\Frame $frame, int $block, Term $value, int $depth, array $seen = []): Term
    {
        if ($seen === [] && $block !== 0 && $this->engine->context->configuration->hasBranchEvaluators && !isset($this->engine->context->evaluationEvidence[$frame->graph->body])) {
            return (new \Deriver\Deriver\Evaluator\Predicate\Controls($this->engine))->apply($frame, $block, $value, $depth);
        }
        return $this->walk($frame, $block, $value, $depth, $seen);
    }

    /**
     * Collects selecting conditions for one value without reentering the guard cache.
     * @param array<int, true> $seen Blocks on the current dependency path
     */
    public function walk(\Deriver\Deriver\Evaluator\Scope\Frame $frame, int $block, Term $value, int $depth, array $seen = []): Term
    {
        if ($block === 0 || isset($seen[$block])) {
            return $value;
        }
        $seen[$block] = true;
        $frame->graph->controls ??= (new \Deriver\Deriver\Evaluator\Index\ControlDependencies())->build($frame->graph, isset($this->engine->context->evaluationEvidence[$frame->graph->body]) ? $this->engine->context : null);
        if ($frame->graph->controls === null) {
            return new Term(\Deriver\Deriver\Evaluator\TermKind::ControlledValue, operands: [$value, $this->engine->context->reference($frame, 'control:' . $block, $frame->graph->body->source, reason: $this->engine->context->stopReason ?? 'BUDGET_EXCEEDED')], attributes: ['reason' => $this->engine->context->stopReason]);
        }
        $alternatives = [];
        foreach ($this->selecting($frame, $block) as [$parent, $child]) {
            $source = (new \Deriver\Deriver\Evaluator\Recurrence\Definitions($this->engine))->predecessor($frame, $parent, $child);
            $alternatives[] = $this->walk($source, $parent, $this->edge($source, $parent, $child, $value, $depth), $depth, $seen);
        }
        if (count($alternatives) <= 1) {
            return $alternatives[0] ?? $value;
        }
        return (new Choices($this->engine->context->provenance))->make(array_map(static fn (Term $alternative): array => [$alternative, []], $alternatives));
    }

    /**
     * Returns the edges whose selection a block requires, one per way of reaching it.
     * An edge already required by another edge's branch is nested inside it, so walking that
     * branch applies both; the remaining edges are alternatives, as with switch fall-through.
     * @return list<array{int, int}> Branch block and selected target
     */
    public function selecting(\Deriver\Deriver\Evaluator\Scope\Frame $frame, int $block): array
    {
        $edges = [];
        foreach ($this->dependencies($frame, $block) as $edge) {
            $edges[$edge[0] . ':' . $edge[1]] = $edge;
        }
        if (count($edges) <= 1) {
            return array_values($edges);
        }
        $nested = [];
        foreach ($edges as $key => [$parent]) {
            $pending = [$parent];
            $visited = [];
            while ($pending !== []) {
                $current = array_pop($pending);
                if (isset($visited[$current])) {
                    continue;
                }
                $visited[$current] = true;
                foreach ($this->dependencies($frame, $current) as $ancestor) {
                    $ancestorKey = $ancestor[0] . ':' . $ancestor[1];
                    if ($ancestorKey !== $key) {
                        $nested[$ancestorKey] = true;
                    }
                    $pending[] = $ancestor[0];
                }
            }
        }
        return array_values(array_diff_key($edges, $nested));
    }

    /**
     * Reads the indexed control dependencies of a block, without loop back edges unless the loop is being evaluated.
     * @return list<array{int, int}>
     */
    private function dependencies(\Deriver\Deriver\Evaluator\Scope\Frame $frame, int $block): array
    {
        $edges = [];
        foreach ($frame->graph->controls[$block] ?? [] as [$parent, $child]) {
            if ($frame->graph->body->blocks[$block]->loopHeader && $parent >= $block && !isset($this->engine->context->evaluationEvidence[$frame->graph->body])) {
                continue;
            }
            $edges[] = [$parent, $child];
        }
        return $edges;
    }

    /**
     * Attaches the decision represented by one predecessor edge.
     * Keeps products beyond the partition budget as unresolved selections.
     */
    public function edge(\Deriver\Deriver\Evaluator\Scope\Frame $frame, int $parent, int $child, Term $value, int $depth): Term
    {
        $end = $frame->graph->body->blocks[$parent]->terminator;
        if ($end->kind !== \Deriver\Deriver\Lowerer\Graph\TerminatorKind::Branch) {
            return $value;
        }
        $expected = $end->targets[0] === $child;
        if ($expected && $this->engine->context->configuration->hasBranchEvaluators && $frame->graph->body->blocks[$parent]->loopHeader && ($frame->iterations[$parent] ?? 0) === 0 && (new \Deriver\Deriver\Evaluator\Recurrence\Definitions($this->engine))->postCondition($frame, $parent)) {
            return $value;
        }
        $frame = (new \Deriver\Deriver\Evaluator\Recurrence\Observation($this->engine))->condition($frame, $parent, $expected, $depth);
        $selector = $frame->identity . ':condition:' . $end->operand;
        $choices = new Choices($this->engine->context->provenance);
        if (!$this->engine->context->configuration->hasBranchEvaluators && !isset($this->engine->context->evaluationEvidence[$frame->graph->body]) && ($frame->graph->definitions[$end->operand]->operation ?? '') !== 'static-initialized') {
            $selected = $this->engine->context->provenance->wrap($value, \Deriver\Deriver\Evaluator\Evidence\EvidenceKind::Choice, $frame->graph->definitions[$end->operand]->source ?? $frame->graph->body->source, ['selector' => $selector, 'branch' => $expected]);
            return $choices->make([[$selected, [$selector => $expected]]]);
        }
        $condition = (new \Deriver\Deriver\Evaluator\Predicate\Branch($this->engine))->condition($frame, $end->operand, $depth);
        $selected = $choices->apply('selection', [$value, $condition], function (array $values) use ($frame, $end, $expected, $selector): Term {
            [$value, $test] = $values;
            $truth = (new Operations())->truth($test);
            if ($truth !== null && $truth !== $expected) {
                return new Term(\Deriver\Deriver\Evaluator\TermKind::Choice);
            }
            $selected = (new \Deriver\Deriver\Evaluator\Search\Interruption())->guard($value, $test);
            return $this->engine->context->provenance->wrap($selected, \Deriver\Deriver\Evaluator\Evidence\EvidenceKind::Choice, $frame->graph->definitions[$end->operand]->source ?? $frame->graph->body->source, ['selector' => $selector, 'branch' => $expected]);
        }, $this->engine->context->budget->partitions);
        return $choices->make([[$selected, [$selector => $expected]]]);
    }
}
