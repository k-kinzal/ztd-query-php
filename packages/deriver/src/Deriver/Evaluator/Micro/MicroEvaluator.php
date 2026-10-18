<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Micro;

use Deriver\Deriver\Evaluator\Index\BodyIndex;
use Deriver\Deriver\Evaluator\Term;

/**
 * Executable contract shared by configured, instruction and call evaluators.
 * @template TResult of BodyIndex|Term|null
 * @visibility parent
 */
interface MicroEvaluator
{
    /**
     * Evaluates the operation, supplies its body, or declines it.
     * @return TResult
     * @throws \Deriver\Exception\EvaluationContractException If a micro evaluator violates its contract
     */
    public function evaluate(Request $request): BodyIndex|Term|null;
}
