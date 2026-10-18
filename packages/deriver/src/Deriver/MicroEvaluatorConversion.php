<?php

declare(strict_types=1);

namespace Deriver\Deriver;

use Deriver\Deriver\Evaluator\Micro\ConfiguredEvaluator;
use Deriver\Deriver\Evaluator\Micro\Request;
use Deriver\Deriver\Evaluator\Term;

/**
 * Converts public micro evaluators at the boundary of the evaluator stage.
 * The evaluator stage never receives DeriverConfig or public evaluator definitions.
 * @visibility namespace
 * @visibility Deriver
 */
final class MicroEvaluatorConversion
{
    /**
     * Captures public semantics in the same executable contract as built-in evaluators.
     */
    public function convert(MicroEvaluator $evaluator): ConfiguredEvaluator
    {
        $operation = $evaluator->operation === EvaluationOperation::Conditional ? 'phi' : $evaluator->operation->value;
        return new ConfiguredEvaluator(
            $evaluator->id,
            $evaluator->version,
            $operation,
            $evaluator->name,
            static fn (Request $request): ?Term => $evaluator->evaluate(new Evaluation($evaluator->operation, $request->name(), $request->source(), $request->input(...), $request->truth(...))),
            $evaluator->priority,
            $evaluator->path,
            $evaluator->start,
            $evaluator->end,
        );
    }
}
