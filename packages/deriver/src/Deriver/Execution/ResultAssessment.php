<?php

declare(strict_types=1);

namespace Deriver\Deriver\Execution;

use Deriver\Deriver\Evaluator\Execution\Completion\Assessment;
use Deriver\Deriver\Evaluator\Execution\Completion\StorageSnapshot;
use Deriver\Deriver\Evaluator\Execution\Context;
use Deriver\Deriver\Evaluator\Term;
use WeakMap;

/**
 * Assesses value precision separately from dependency closure and enumeration.
 * @visibility parent
 */
final class ResultAssessment
{
    /**
     * Computes independent quality axes from explicit reasons and result terms.
     * @param Context $context Completed query
     * @return Assessment Independent measures
     */
    public function assess(Context $context): Assessment
    {
        $opaque = false;
        $abstract = false;
        $concrete = true;
        foreach ($context->normal as $alternative) {
            foreach ($alternative->values as $value) {
                $precision = $this->precision($value, $alternative->storage);
                $opaque = $opaque || $precision === \Deriver\Deriver\Evaluator\Execution\Completion\Quality\ValuePrecision::Opaque;
                $abstract = $abstract || $precision === \Deriver\Deriver\Evaluator\Execution\Completion\Quality\ValuePrecision::Abstract;
                $concrete = $concrete && $value->isConcrete();
            }
        }
        foreach ($context->exceptional as $alternative) {
            $precision = $this->precision($alternative->exception, $alternative->storage);
            $opaque = $opaque || $precision === \Deriver\Deriver\Evaluator\Execution\Completion\Quality\ValuePrecision::Opaque;
            $abstract = $abstract || $precision === \Deriver\Deriver\Evaluator\Execution\Completion\Quality\ValuePrecision::Abstract;
            $concrete = $concrete && $alternative->exception->isConcrete();
        }
        $codes = array_map(static fn ($frontier): string => $frontier->code, array_values($context->frontiers));
        $open = array_diff($codes, ['EXTERNAL_INPUT', 'WIDENED', 'PHP_WARNING']) !== [];
        $unavailable = in_array('UNSUPPORTED_LANGUAGE_FEATURE', $codes, true) || in_array('UNSPECIFIED_EVALUATION_ORDER', $codes, true);
        return new Assessment($open ? \Deriver\Deriver\Evaluator\Execution\Completion\Quality\DependencyClosure::Open : \Deriver\Deriver\Evaluator\Execution\Completion\Quality\DependencyClosure::Closed, $opaque ? \Deriver\Deriver\Evaluator\Execution\Completion\Quality\ValuePrecision::Opaque : ($abstract || in_array('WIDENED', $codes, true) ? \Deriver\Deriver\Evaluator\Execution\Completion\Quality\ValuePrecision::Abstract : \Deriver\Deriver\Evaluator\Execution\Completion\Quality\ValuePrecision::ExactSymbolic), in_array('CORRELATION_RELAXED', $codes, true) ? \Deriver\Deriver\Evaluator\Execution\Completion\Quality\Correlation::Relaxed : \Deriver\Deriver\Evaluator\Execution\Completion\Quality\Correlation::Preserved, $unavailable ? \Deriver\Deriver\Evaluator\Execution\Completion\Quality\Coverage::Unavailable : \Deriver\Deriver\Evaluator\Execution\Completion\Quality\Coverage::OverApproximation, $concrete && !$open ? \Deriver\Deriver\Evaluator\Execution\Completion\Quality\Enumeration::FiniteExhaustive : \Deriver\Deriver\Evaluator\Execution\Completion\Quality\Enumeration::NotEnumerated);
    }

    /**
     * Finds opaque dependencies within a partial structure.
     * @param Term $value Value graph
     * @return bool Whether any projected component is opaque
     */
    public function opaque(Term $value): bool
    {
        return $this->precision($value) === \Deriver\Deriver\Evaluator\Execution\Completion\Quality\ValuePrecision::Opaque;
    }

    /**
     * Classifies the graph without repeatedly expanding shared subexpressions.
     * @param Term $value Observed value
     * @param StorageSnapshot|null $storage Reachable storage belonging to the observed alternative
     * @return \Deriver\Deriver\Evaluator\Execution\Completion\Quality\ValuePrecision Precision of the observed graph
     */
    public function precision(Term $value, ?StorageSnapshot $storage = null): \Deriver\Deriver\Evaluator\Execution\Completion\Quality\ValuePrecision
    {
        $visited = new WeakMap();
        $pending = [$value];
        $abstract = false;
        while ($pending !== []) {
            $current = array_pop($pending);
            if (isset($visited[$current])) {
                continue;
            }
            $visited[$current] = true;
            if ($current->kind === \Deriver\Deriver\Evaluator\TermKind::Opaque || $current->kind === \Deriver\Deriver\Evaluator\TermKind::Uninitialized) {
                return \Deriver\Deriver\Evaluator\Execution\Completion\Quality\ValuePrecision::Opaque;
            }
            $abstract = $abstract || $current->kind === \Deriver\Deriver\Evaluator\TermKind::Abstract;
            array_push($pending, ...array_values($current->operands));
            if (is_string($current->literal)) {
                $roots = match ($current->kind) {
                    \Deriver\Deriver\Evaluator\TermKind::Object => ['object:' . $current->literal, 'evaluator:' . $current->literal],
                    \Deriver\Deriver\Evaluator\TermKind::Cell => [$current->literal],
                    default => [],
                };
                foreach ($roots as $root) {
                    if (isset($storage->cells[$root])) {
                        $pending[] = $storage->cells[$root];
                    }
                }
            }
        }
        return $abstract ? \Deriver\Deriver\Evaluator\Execution\Completion\Quality\ValuePrecision::Abstract : \Deriver\Deriver\Evaluator\Execution\Completion\Quality\ValuePrecision::ExactSymbolic;
    }
}
