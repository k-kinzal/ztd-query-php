<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Call;

use Deriver\Exception\EvaluationException;

/**
 * Supplies API meaning as a declarative plan; the core binds and evaluates calls.
 *
 * @visibility root
 * @example Describing a evaluator identity
 *     (new \Deriver\Deriver\Evaluator\Call\CallDescriptor('keys', '1', 'key'))->id // => 'keys'
 */
interface CallEvaluator
{
    /**
     * Identifies the evaluator and its supported signature.
     * @return CallDescriptor Stable registration metadata
     * @throws EvaluationException If trusted plugin code fails
     */
    public function descriptor(): CallDescriptor;

    /**
     * Selects a plan using call metadata rather than executing application code.
     * @param CallDescription $call Normalized call metadata
     * @return CallDecision Handled, declined, or explicitly unsupported
     * @throws EvaluationException If trusted plugin code fails
     */
    public function evaluate(CallDescription $call): CallDecision;
}
