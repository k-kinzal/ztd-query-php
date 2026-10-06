<?php

declare(strict_types=1);

namespace Deriver\Analysis;

use Deriver\Evaluation\Context;
use Deriver\Result\Assessment;
use Deriver\Result\StorageSnapshot;
use Deriver\Value\Term;
use WeakMap;

/**
 * Assesses value precision separately from dependency closure and enumeration.
 * @visibility root
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
                $opaque = $opaque || $precision === 'opaque';
                $abstract = $abstract || $precision === 'abstract';
                $concrete = $concrete && $value->isConcrete();
            }
        }
        foreach ($context->exceptional as $alternative) {
            $precision = $this->precision($alternative->exception, $alternative->storage);
            $opaque = $opaque || $precision === 'opaque';
            $abstract = $abstract || $precision === 'abstract';
            $concrete = $concrete && $alternative->exception->isConcrete();
        }
        $codes = array_map(static fn ($frontier): string => $frontier->code, array_values($context->frontiers));
        $open = array_diff($codes, ['EXTERNAL_INPUT', 'WIDENED', 'PHP_WARNING']) !== [];
        $unavailable = in_array('UNSUPPORTED_LANGUAGE_FEATURE', $codes, true) || in_array('UNSPECIFIED_EVALUATION_ORDER', $codes, true);
        return new Assessment($open ? 'open' : 'closed', $opaque ? 'opaque' : ($abstract || in_array('WIDENED', $codes, true) ? 'abstract' : 'exact-symbolic'), in_array('CORRELATION_RELAXED', $codes, true) ? 'relaxed' : 'preserved', $unavailable ? 'unavailable' : 'over-approximation', $concrete && !$open ? 'finite-exhaustive' : 'not-enumerated');
    }

    /**
     * Finds opaque dependencies within a partial structure.
     * @param Term $value Value graph
     * @return bool Whether any projected component is opaque
     */
    public function opaque(Term $value): bool
    {
        return $this->precision($value) === 'opaque';
    }

    /**
     * Classifies the graph without repeatedly expanding shared subexpressions.
     * @param Term $value Observed value
     * @param StorageSnapshot|null $storage Reachable storage belonging to the observed alternative
     * @return string exact-symbolic, abstract, or opaque
     */
    public function precision(Term $value, ?StorageSnapshot $storage = null): string
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
            if ($current->kind === 'opaque' || $current->kind === 'uninitialized') {
                return 'opaque';
            }
            $abstract = $abstract || $current->kind === 'abstract';
            array_push($pending, ...array_values($current->operands));
            if (is_string($current->literal)) {
                $roots = match ($current->kind) {
                    'object' => ['object:' . $current->literal, 'model:' . $current->literal],
                    'cell' => [$current->literal],
                    default => [],
                };
                foreach ($roots as $root) {
                    if (isset($storage->cells[$root])) {
                        $pending[] = $storage->cells[$root];
                    }
                }
            }
        }
        return $abstract ? 'abstract' : 'exact-symbolic';
    }
}
