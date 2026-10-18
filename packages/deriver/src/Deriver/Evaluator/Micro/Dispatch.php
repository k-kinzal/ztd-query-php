<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Micro;

use Deriver\Deriver\Evaluator\Index\BodyIndex;
use Deriver\Deriver\Evaluator\Term;

/**
 * Composes configured and built-in micro evaluators through one contract.
 * @visibility parent
 */
final class Dispatch
{
    /**
     * Tries configured evaluators in priority order, then the built-in evaluator.
     * @template TResult of BodyIndex|Term|null
     * @param MicroEvaluator<TResult> $builtin Built-in implementation of the same contract
     * @return Term|TResult
     */
    public function evaluate(Request $request, MicroEvaluator $builtin): BodyIndex|Term|null
    {
        foreach ($request->engine->context->configuration->evaluators as $evaluator) {
            $attempt = new Request($request->engine, $request->frame, $request->instruction, $request->depth, $request->function, $request->site);
            $result = $evaluator->evaluate($attempt);
            if ($result !== null) {
                return $result;
            }
        }
        return $builtin->evaluate($request);
    }
}
