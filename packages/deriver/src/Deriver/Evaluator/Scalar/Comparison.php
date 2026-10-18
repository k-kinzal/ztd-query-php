<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Scalar;

use WeakMap;

/**
 * Compares concrete values and retains symbolic predicates for path constraints.
 * @visibility root
 */
final class Comparison
{
    /**
     * @param int|null $floatPrecision Captured target precision for float-to-string comparisons; null when unknown
     */
    public function __construct(public readonly ?int $floatPrecision = null)
    {
    }

    /**
     * Evaluates strict scalar equality and ordered comparisons.
     * @param string $operator PHP comparison
     * @param \Deriver\Deriver\Evaluator\Term $left Left operand
     * @param \Deriver\Deriver\Evaluator\Term $right Right operand
     * @return \Deriver\Deriver\Evaluator\Term Boolean or spaceship result
     */
    public function apply(string $operator, \Deriver\Deriver\Evaluator\Term $left, \Deriver\Deriver\Evaluator\Term $right): \Deriver\Deriver\Evaluator\Term
    {
        if ($left->kind === \Deriver\Deriver\Evaluator\TermKind::Constant && $right->kind === \Deriver\Deriver\Evaluator\TermKind::Constant) {
            return \Deriver\Deriver\Evaluator\Term::constant($this->scalar($operator, $left->literal, $right->literal));
        }
        if ($left->kind === \Deriver\Deriver\Evaluator\TermKind::Array && $right->kind === \Deriver\Deriver\Evaluator\TermKind::Array && $left->isConcrete() && $right->isConcrete() && ($operator === '===' || $operator === '!==')) {
            $equal = $this->identical($left, $right);
            return \Deriver\Deriver\Evaluator\Term::constant($operator === '===' ? $equal : !$equal);
        }
        $identity = $this->object($operator, $left, $right);
        if ($identity !== null) {
            return $identity;
        }
        return new \Deriver\Deriver\Evaluator\Term(\Deriver\Deriver\Evaluator\TermKind::Binary, $operator, [$left, $right], ['type' => $operator === '<=>' ? 'int' : 'bool']);
    }

    /**
     * Proves concrete PHP array identity without expanding shared immutable subgraphs.
     * @param \Deriver\Deriver\Evaluator\Term $left Concrete scalar or array
     * @param \Deriver\Deriver\Evaluator\Term $right Concrete scalar or array
     * @return bool Whether PHP strict equality is established
     */
    public function identical(\Deriver\Deriver\Evaluator\Term $left, \Deriver\Deriver\Evaluator\Term $right): bool
    {
        if (!$left->isConcrete() || !$right->isConcrete()) {
            return false;
        }
        /** @var WeakMap<\Deriver\Deriver\Evaluator\Term, WeakMap<\Deriver\Deriver\Evaluator\Term, true>> $seen */
        $seen = new WeakMap();
        /** @var list<array{\Deriver\Deriver\Evaluator\Term, \Deriver\Deriver\Evaluator\Term}> $pending */
        $pending = [[$left, $right]];
        while ($pending !== []) {
            [$a, $b] = array_pop($pending);
            if ($a->kind !== $b->kind) {
                return false;
            }
            if ($a->kind === \Deriver\Deriver\Evaluator\TermKind::Constant) {
                if ($a->literal !== $b->literal) {
                    return false;
                }
                continue;
            }
            /** @var WeakMap<\Deriver\Deriver\Evaluator\Term, true> $partners */
            $partners = new WeakMap();
            $partners = $seen[$a] ?? $partners;
            if (isset($partners[$b])) {
                continue;
            }
            if (array_keys($a->operands) !== array_keys($b->operands)) {
                return false;
            }
            $partners[$b] = true;
            $seen[$a] = $partners;
            foreach ($a->operands as $key => $operand) {
                $pending[] = [$operand, $b->operands[$key]];
            }
        }
        return true;
    }

    /**
     * Implements scalar comparisons whose semantics are stable in the target profile.
     * @param string $operator Comparison operator
     * @param scalar|null $a Left operand
     * @param scalar|null $b Right operand
     * @return bool|int \Deriver\Deriver\Evaluator\Scalar\Comparison result
     */
    public function scalar(string $operator, int|float|string|bool|null $a, int|float|string|bool|null $b): bool|int
    {
        $compare = static fn (): bool|int => match ($operator) {
            '===' => $a === $b,
            '!==' => $a !== $b,
            '==' => ($a <=> $b) === 0,
            '!=' => ($a <=> $b) !== 0,
            '<' => $a < $b,
            '<=' => $a <= $b,
            '>' => $a > $b,
            '>=' => $a >= $b,
            '<=>' => $a <=> $b,
            default => false,
        };
        return (is_float($a) && is_string($b)) || (is_string($a) && is_float($b)) ? (new FloatConversion($this->floatPrecision))->within($compare) : $compare();
    }

    /**
     * Compares object and closure identities independently of captured values.
     * @param string $operator Comparison operator
     * @param \Deriver\Deriver\Evaluator\Term $left Left value
     * @param \Deriver\Deriver\Evaluator\Term $right Right value
     * @return \Deriver\Deriver\Evaluator\Term|null Proven identity comparison, or no applicable identity rule
     */
    public function object(string $operator, \Deriver\Deriver\Evaluator\Term $left, \Deriver\Deriver\Evaluator\Term $right): ?\Deriver\Deriver\Evaluator\Term
    {
        if (!in_array($operator, ['===', '!=='], true) || $left->kind !== $right->kind) {
            return null;
        }
        if ($left->kind === \Deriver\Deriver\Evaluator\TermKind::Closure && isset($left->attributes['identity'], $right->attributes['identity'])) {
            $equal = $left->attributes['identity'] === $right->attributes['identity'];
        } elseif (in_array($left->kind, [\Deriver\Deriver\Evaluator\TermKind::Object, \Deriver\Deriver\Evaluator\TermKind::Enum], true)) {
            $equal = $left->literal === $right->literal;
        } else {
            return null;
        }
        return \Deriver\Deriver\Evaluator\Term::constant($operator === '===' ? $equal : !$equal);
    }
}
