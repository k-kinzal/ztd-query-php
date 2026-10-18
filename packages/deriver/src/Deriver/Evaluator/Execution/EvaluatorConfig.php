<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution;

use Deriver\Deriver\Evaluator\TargetProfile;
use Deriver\Deriver\Evaluator\Term;

/**
 * Semantic inputs for ordered evaluation.
 * @visibility root
 */
final class EvaluatorConfig
{
    /**
     * @param array<string, Term> $environment Supplied symbolic inputs
     */
    public function __construct(
        public readonly TargetProfile $target = new TargetProfile(),
        public readonly array $environment = [],
        public readonly bool $standardEvaluators = true,
    ) {
    }
}
