<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Predicate;

use Deriver\Deriver\Evaluator\Enumeration\Choices;
use Deriver\Deriver\Evaluator\Evaluator;
use Deriver\Deriver\Evaluator\Expression\Guards;
use Deriver\Deriver\Evaluator\Scope\Frame;
use Deriver\Deriver\Evaluator\Search\Interruption;
use Deriver\Deriver\Evaluator\Term;

/**
 * Shares selecting conditions between reads and values in the same source block.
 * @visibility parent
 */
final class Controls
{
    /**
     * Shares the query's predicate dependencies and proof ledger.
     */
    public function __construct(private readonly Evaluator $engine)
    {
    }

    /**
     * Applies a block's shared feasibility to a demanded candidate.
     */
    public function apply(Frame $frame, int $block, Term $value, int $depth): Term
    {
        $context = $this->engine->context;
        $conditions = $context->search->resolve(
            $frame->identity . ':controls:' . $block,
            fn (): Term => (new Guards($this->engine))->walk($frame, $block, Term::constant(true), $depth),
            fn (): Term => $context->reference($frame, 'control:' . $block, $frame->graph->body->source, reason: 'CYCLE'),
        );
        return (new Choices($context->provenance))->apply('control', [$value, $conditions], static fn (array $values): Term => (new Interruption())->guard($values[0], $values[1]), $context->budget->partitions);
    }
}
