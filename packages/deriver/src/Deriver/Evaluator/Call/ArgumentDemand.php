<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Call;

/**
 * Requests only the inputs needed to select a candidate evaluator's plan.
 * Plan parameter expressions request their own dependencies lazily.
 * @visibility root
 * @example Checking a evaluator selection contract
 *     $evaluator = new class implements \Deriver\Deriver\Evaluator\Call\ArgumentDemand {
 *         public function descriptor(): \Deriver\Deriver\Evaluator\Call\CallDescriptor {
 *             return new \Deriver\Deriver\Evaluator\Call\CallDescriptor('example.fast', '1', 'formatValue');
 *         }
 *         public function demand(\Deriver\Deriver\Evaluator\Call\CallDescription $call): array { return ['mode']; }
 *         public function evaluate(\Deriver\Deriver\Evaluator\Call\CallDescription $call): \Deriver\Deriver\Evaluator\Call\CallDecision {
 *             return \Deriver\Deriver\Evaluator\Call\CallDecision::declined();
 *         }
 *     };
 *     $evaluator->demand(new \Deriver\Deriver\Evaluator\Call\CallDescription('formatValue', new \Deriver\Deriver\Evaluator\Declaration\Signature(), new \Deriver\Deriver\Evaluator\TargetProfile())) // => ['mode']
 */
interface ArgumentDemand extends CallEvaluator
{
    /**
     * @param CallDescription $call Declaration metadata and formal argument handles
     * @return list<string> Signature parameter names needed by describe()
     * @throws \Deriver\Exception\EvaluationContractException If the evaluator cannot select its required inputs
     */
    public function demand(CallDescription $call): array;
}
