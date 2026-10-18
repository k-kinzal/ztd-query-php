<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Recurrence;

use Deriver\Deriver\Evaluator\Enumeration\Choices;
use Deriver\Deriver\Evaluator\Evaluator;
use Deriver\Deriver\Evaluator\Predicate\Branch;
use Deriver\Deriver\Evaluator\Scalar\Operations;
use Deriver\Deriver\Evaluator\Scope\Frame;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Evaluator\TermKind;

/**
 * Prunes source loop candidates with entry and one-update constraints.
 * No further iteration is opened when the loop can repeat.
 * @visibility parent
 */
final class Bounds
{
    /**
     * Uses the configured branch evaluator for both constraint observations.
     */
    public function __construct(private readonly Evaluator $engine)
    {
    }

    /**
     * Shares bounds across every demanded storage location in a loop.
     */
    public function of(Frame $frame, int $header, int $depth): Term
    {
        if (!$this->engine->context->configuration->hasBranchEvaluators || isset($this->engine->context->evaluationEvidence[$frame->graph->body])) {
            return $this->result(0, -1, []);
        }
        return $this->engine->context->search->resolve(
            $frame->identity . ':loop-bounds:' . $header,
            fn (): Term => $this->derive($frame, $header, $depth),
            fn (): Term => $this->result(0, -1, []),
        );
    }

    /**
     * Tests only entry and the first update, retaining uncertainty conservatively.
     */
    public function derive(Frame $frame, int $header, int $depth): Term
    {
        $condition = $frame->graph->body->blocks[$header]->terminator->operand;
        $post = (new Definitions($this->engine))->postCondition($frame, $header);
        $branch = new Branch($this->engine);
        $entry = $post ? Term::constant(true) : $branch->condition($frame->iteration($header, 0), $condition, $depth);
        $truth = $this->known($entry);
        if ($truth === false) {
            return $this->result(0, 0, [$entry]);
        }
        if ($truth === null) {
            return $this->result(0, -1, [$entry]);
        }
        $next = $branch->condition($frame->iteration($header, $post ? 0 : 1), $condition, $depth);
        return $this->result(1, $this->known($next) === false ? 1 : -1, [$entry, $next]);
    }

    /**
     * An unresolved or mixed result cannot prove a singleton condition.
     */
    public function known(Term $value): ?bool
    {
        $known = null;
        foreach ((new Choices())->alternatives($value) as [$candidate]) {
            $truth = (new Operations())->truth($candidate);
            if ($truth === null || $known !== null && $known !== $truth) {
                return null;
            }
            $known = $truth;
        }
        return $known;
    }

    /**
     * @param list<Term> $inputs Evidence establishing the constraint
     */
    public function result(int $minimum, int $maximum, array $inputs): Term
    {
        return $this->engine->context->provenance->operation(new Term(TermKind::Operation, 'loop-bounds', attributes: ['minimum' => $minimum, 'maximum' => $maximum]), $inputs, 'loop-bounds');
    }
}
