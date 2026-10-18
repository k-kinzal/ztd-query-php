<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution\Completion;

/**
 * Independent measures of completeness, precision, and correlation.
 *
 * @visibility root
 * @example Inspecting the contract
 *     (new \Deriver\Deriver\Evaluator\Execution\Completion\Assessment())->closure === \Deriver\Deriver\Evaluator\Execution\Completion\Quality\DependencyClosure::Closed // => true
 */
final class Assessment
{
    /**
     * @param Quality\DependencyClosure $closure closure
     * @param Quality\ValuePrecision $precision precision
     * @param Quality\Correlation $correlation correlation
     * @param Quality\Coverage $coverage coverage
     * @param Quality\Enumeration $enumeration enumeration
     */
    public function __construct(
        public readonly Quality\DependencyClosure $closure = Quality\DependencyClosure::Closed,
        public readonly Quality\ValuePrecision $precision = Quality\ValuePrecision::ExactSymbolic,
        public readonly Quality\Correlation $correlation = Quality\Correlation::Preserved,
        public readonly Quality\Coverage $coverage = Quality\Coverage::OverApproximation,
        public readonly Quality\Enumeration $enumeration = Quality\Enumeration::NotEnumerated,
    ) {
    }
}
