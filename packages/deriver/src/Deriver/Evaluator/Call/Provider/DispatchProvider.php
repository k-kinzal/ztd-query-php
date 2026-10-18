<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Call\Provider;

/**
 * Adds justified library dispatch candidates without mutating application memory.
 * @visibility root
 * @example Partial candidates never imply a closed world
 *     (new \Deriver\Deriver\Evaluator\Call\Provider\DispatchDecision())->targets // => []
 */
interface DispatchProvider extends Provider
{
    /**
     * @param DispatchRequest $request Evaluated call metadata
     * @return DispatchDecision Candidates and explicit completeness contract
     * @throws \Deriver\Exception\EvaluationException If trusted plugin code fails
     */
    public function resolve(DispatchRequest $request): DispatchDecision;
}
