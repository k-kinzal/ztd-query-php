<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Expression;

use Deriver\Deriver\Evaluator\Evaluator;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Evaluator\TermKind;

/**
 * Evaluates return alternatives with their path conditions.
 * @visibility parent
 */
final class ReturnEvaluator
{
    /**
     * Shares dependency scheduling and evaluation rules with the parent evaluator.
     */
    public function __construct(public readonly Evaluator $engine)
    {
    }

    /**
     * Makes feasible alternatives available to the shared search.
     */
    public function evaluate(\Deriver\Deriver\Evaluator\Scope\Frame $frame, int $depth, bool $truth = false, int $wanted = 3, ?\Deriver\Deriver\Evaluator\Enumeration\Finite\Demand $finite = null): Term
    {
        $domain = $finite ?? ($truth ? null : (new \Deriver\Deriver\Evaluator\Enumeration\Finite\Declarations())->domain($this->engine, $frame->graph->body->returnType, $frame->graph->body->className));
        $branches = [];
        foreach ($frame->graph->returns() as [$block, $register]) {
            $branches[] = function (int $wanted = 3, ?\Deriver\Deriver\Evaluator\Enumeration\Finite\Demand $finite = null) use ($frame, $depth, $block, $register, $truth): Term {
                if ($this->engine->context->configuration->hasBranchEvaluators || isset($this->engine->context->evaluationEvidence[$frame->graph->body])) {
                    $conditions = (new Guards($this->engine))->at($frame, $block, Term::constant(true), $depth);
                    if ($conditions->kind === TermKind::Choice && $conditions->operands === []) {
                        return new Term(TermKind::Choice);
                    }
                }
                $demand = $finite ?? ($truth && !in_array($frame->graph->body->returnType, ['', 'mixed'], true) ? \Deriver\Deriver\Evaluator\Enumeration\Finite\Demand::truth($wanted) : null);
                $demand = $demand?->through($this->engine, $frame->graph->body->returnType, $frame->graph->body->strict, $frame->graph->body->className);
                $value = $truth || $demand !== null ? (new \Deriver\Deriver\Evaluator\Predicate\Truth($this->engine, $wanted, $demand))->value($frame, $register, $depth) : $this->engine->value($frame, $register, $depth);
                $value = $frame->graph->body->blocks[$block]->terminator->kind === \Deriver\Deriver\Lowerer\Graph\TerminatorKind::Throw ? new Term(TermKind::Throwable, (string) ($value->attributes['type'] ?? 'Throwable'), [$value]) : (new \Deriver\Deriver\Evaluator\Language\DeclarationCoercion())->check($this->engine, $value, $frame->graph->body->returnType, $frame->graph->body->strict, $frame->graph->body->className);
                $value = (new \Deriver\Deriver\Evaluator\Language\Catches())->resolve($this->engine, $frame, $block, $value, $depth);
                $value = (new \Deriver\Deriver\Evaluator\Language\Finalizers())->apply($this->engine, $frame, $block, $value, $depth);
                return (new Guards($this->engine))->at($frame, $block, $value, $depth);
            };
        }
        if ($domain !== null) {
            return $this->engine->context->provenance->evaluator($domain->collect($branches, $this->engine->context->provenance), $this->engine->context->evaluationEvidence[$frame->graph->body] ?? null);
        }
        if ($truth) {
            return (new \Deriver\Deriver\Evaluator\Predicate\Projection($this->engine->context->provenance))->collect($branches, $wanted);
        }
        $alternatives = array_map(static fn (Term $value): array => [$value, []], $this->engine->context->search->all($branches));
        return $this->engine->context->provenance->evaluator((new \Deriver\Deriver\Evaluator\Enumeration\Choices($this->engine->context->provenance))->make($alternatives), $this->engine->context->evaluationEvidence[$frame->graph->body] ?? null);
    }

}
