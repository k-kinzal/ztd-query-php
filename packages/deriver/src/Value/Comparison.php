<?php

declare(strict_types=1);

namespace Deriver\Value;

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
     * @param Term $left Left operand
     * @param Term $right Right operand
     * @return Term Boolean or spaceship result
     */
    public function apply(string $operator, Term $left, Term $right): Term
    {
        if ($left->kind === 'constant' && $right->kind === 'constant') {
            return Term::constant($this->scalar($operator, $left->literal, $right->literal), $left->isSecret() || $right->isSecret());
        }
        if ($left->kind === 'array' && $right->kind === 'array' && $left->isConcrete() && $right->isConcrete() && ($operator === '===' || $operator === '!==')) {
            $equal = $this->identical($left, $right);
            return Term::constant($operator === '===' ? $equal : !$equal, $left->isSecret() || $right->isSecret());
        }
        $identity = $this->object($operator, $left, $right);
        if ($identity !== null) {
            return $identity;
        }
        return new Term('binary', $operator, [$left, $right], ['type' => $operator === '<=>' ? 'int' : 'bool']);
    }

    /**
     * Proves concrete PHP array identity without expanding shared immutable subgraphs.
     * @param Term $left Concrete scalar or array
     * @param Term $right Concrete scalar or array
     * @return bool Whether PHP strict equality is established
     */
    public function identical(Term $left, Term $right): bool
    {
        if (!$left->isConcrete() || !$right->isConcrete()) {
            return false;
        }
        /** @var WeakMap<Term, WeakMap<Term, true>> $seen */
        $seen = new WeakMap();
        /** @var list<array{Term, Term}> $pending */
        $pending = [[$left, $right]];
        while ($pending !== []) {
            [$a, $b] = array_pop($pending);
            if ($a->kind !== $b->kind) {
                return false;
            }
            if ($a->kind === 'constant') {
                if ($a->literal !== $b->literal) {
                    return false;
                }
                continue;
            }
            /** @var WeakMap<Term, true> $partners */
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
     * @return bool|int Comparison result
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
     * @param Term $left Left value
     * @param Term $right Right value
     * @return Term|null Proven identity comparison, or no applicable identity rule
     */
    public function object(string $operator, Term $left, Term $right): ?Term
    {
        if (!in_array($operator, ['===', '!=='], true) || $left->kind !== $right->kind) {
            return null;
        }
        if ($left->kind === 'closure' && isset($left->attributes['identity'], $right->attributes['identity'])) {
            $equal = $left->attributes['identity'] === $right->attributes['identity'];
        } elseif (in_array($left->kind, ['object', 'enum'], true)) {
            $equal = $left->literal === $right->literal;
        } else {
            return null;
        }
        return Term::constant($operator === '===' ? $equal : !$equal, $left->isSecret() || $right->isSecret());
    }
}
