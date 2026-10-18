<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Builtin;

use Deriver\Deriver\Evaluator\Scalar\FloatConversion;
use Deriver\Deriver\Evaluator\Term;

/**
 * Sorts concrete scalar arrays with locale-independent PHP sorting modes.
 * @visibility root
 */
final class Sorting
{
    /**
     * @param int|null $floatPrecision Captured target precision for float-to-string conversion; null when unknown
     */
    public function __construct(public readonly ?int $floatPrecision = null)
    {
    }

    /**
     * Produces the reindexed array; the declarative evaluator performs its reference write.
     * @param list<Term> $values Bound array and flags
     * @return Term Reindexed array or unsupported-case residual
     */
    public function apply(array $values): Term
    {
        $array = $values[0] ?? Term::constant(null);
        $flags = $values[1] ?? Term::constant(0);
        if ($array->kind !== \Deriver\Deriver\Evaluator\TermKind::Array || !$array->isConcrete() || $flags->kind !== \Deriver\Deriver\Evaluator\TermKind::Constant || !in_array($flags->literal, [0, 1, 2, 10], true)) {
            return Term::opaque('UNSUPPORTED_EVALUATOR_CASE', 'array', $values);
        }
        $native = [];
        foreach ($array->operands as $value) {
            if ($value->kind !== \Deriver\Deriver\Evaluator\TermKind::Constant) {
                return Term::opaque('UNSUPPORTED_EVALUATOR_CASE', 'array', $values);
            }
            $native[] = $value->literal;
        }
        $flags = $flags->literal;
        (new FloatConversion($this->floatPrecision))->within(static function () use (&$native, $flags): bool {
            return sort($native, $flags);
        });
        return Term::fromNative($native);
    }
}
