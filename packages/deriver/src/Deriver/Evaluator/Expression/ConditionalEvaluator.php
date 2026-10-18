<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Expression;

use Deriver\Deriver\Evaluator\Evaluator;
use Deriver\Deriver\Evaluator\Scalar\Operations;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Lowerer\Graph\Instruction;

/**
 * Derives branch values without requiring source predicates to be evaluated.
 * @visibility parent
 */
final class ConditionalEvaluator
{
    /**
     * Shares dependency scheduling and evaluation rules with the parent evaluator.
     */
    public function __construct(public readonly Evaluator $engine)
    {
    }

    /**
     * Keeps both source alternatives; explicit evaluator plans may select a branch.
     */
    public function evaluate(\Deriver\Deriver\Evaluator\Scope\Frame $frame, Instruction $instruction, int $depth, bool $truth = false, int $wanted = 3, ?\Deriver\Deriver\Evaluator\Enumeration\Finite\Demand $finite = null): Term
    {
        if (!$this->engine->context->configuration->hasBranchEvaluators && !isset($this->engine->context->evaluationEvidence[$frame->graph->body])) {
            return $this->alternatives($frame, $instruction, $depth, $truth, $wanted, $finite);
        }
        $condition = (new \Deriver\Deriver\Evaluator\Predicate\Branch($this->engine))->condition($frame, $instruction->operands[2], $depth);
        $branches = [];
        $guards = [];
        foreach ((new \Deriver\Deriver\Evaluator\Enumeration\Choices($this->engine->context->provenance))->alternatives($condition) as [$test, $guard]) {
            $testTruth = (new Operations())->truth($test);
            foreach ([true, false] as $expected) {
                if ($testTruth !== null && $testTruth !== $expected) {
                    continue;
                }
                $guard[$frame->identity . ':condition:' . $instruction->operands[2]] = $expected;
                $guards[] = $guard;
                $branches[] = function (int $wanted = 3, ?\Deriver\Deriver\Evaluator\Enumeration\Finite\Demand $finite = null) use ($frame, $instruction, $depth, $expected, $test, $truth): Term {
                    $value = ($truth ? (new \Deriver\Deriver\Evaluator\Predicate\Truth($this->engine, $wanted, $finite))->value($frame, $instruction->operands[$expected ? 0 : 1], $depth) : $this->engine->value($frame, $instruction->operands[$expected ? 0 : 1], $depth));
                    $value = $this->engine->context->provenance->operation((new \Deriver\Deriver\Evaluator\Search\Interruption())->guard($value, $test), [$test], 'selection');
                    $value = $this->engine->context->provenance->wrap($value, \Deriver\Deriver\Evaluator\Evidence\EvidenceKind::Choice, $instruction->source, ['selector' => $frame->identity . ':condition:' . $instruction->operands[2], 'branch' => $expected]);

                    return $value;
                };

            }
        }
        if ($truth) {
            return (new \Deriver\Deriver\Evaluator\Predicate\Projection($this->engine->context->provenance, $finite))->collect($branches, $wanted);
        }
        $values = $this->engine->context->search->all($branches);
        $alternatives = array_map(static fn (Term $value, array $guard): array => [$value, $guard], $values, $guards);
        return (new \Deriver\Deriver\Evaluator\Enumeration\Choices($this->engine->context->provenance))->make($alternatives);
    }

    /**
     * Keeps predicate-free source alternatives lazy in either demand domain.
     */
    public function alternatives(\Deriver\Deriver\Evaluator\Scope\Frame $frame, Instruction $instruction, int $depth, bool $truth, int $wanted, ?\Deriver\Deriver\Evaluator\Enumeration\Finite\Demand $finite = null): Term
    {
        $selector = $frame->identity . ':condition:' . $instruction->operands[2];
        $branches = [];
        foreach ([true, false] as $expected) {
            $branches[] = fn (int $wanted = 3, ?\Deriver\Deriver\Evaluator\Enumeration\Finite\Demand $finite = null): Term => $this->engine->context->provenance->wrap(
                ($truth ? (new \Deriver\Deriver\Evaluator\Predicate\Truth($this->engine, $wanted, $finite))->value($frame, $instruction->operands[$expected ? 0 : 1], $depth) : $this->engine->value($frame, $instruction->operands[$expected ? 0 : 1], $depth)),
                \Deriver\Deriver\Evaluator\Evidence\EvidenceKind::Choice,
                $instruction->source,
                ['selector' => $selector, 'branch' => $expected],
            );
        }
        if ($truth) {
            return (new \Deriver\Deriver\Evaluator\Predicate\Projection($this->engine->context->provenance, $finite))->collect($branches, $wanted);
        }
        $values = $this->engine->context->search->all($branches);
        return (new \Deriver\Deriver\Evaluator\Enumeration\Choices($this->engine->context->provenance))->make([
            [$values[0], [$selector => true]], [$values[1], [$selector => false]],
        ]);
    }

}
