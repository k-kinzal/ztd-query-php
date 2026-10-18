<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Domain;

use Deriver\Deriver\Evaluator\Scalar\Identity;
use Deriver\Deriver\Evaluator\Scalar\StringPrefix;
use Deriver\Deriver\Evaluator\Term;
use WeakMap;

/**
 * Finite abstract facts for inclusion and widening, separate from expression identity.
 * @visibility root
 */
final class Lattice
{
    /**
     * @var WeakMap<Term, WeakMap<Term, bool>> Inclusion facts for immutable term pairs.
     */
    private WeakMap $pairs;
    /**
     * Structural identities shared by comparisons within this lattice operation.
     */
    private readonly Identity $identity;

    /**
     * @param array<string, AbstractDomain> $domains Explicitly registered lattices
     */
    public function __construct(public readonly array $domains = [])
    {
        $this->pairs = new WeakMap();
        $this->identity = new Identity();
    }

    /**
     * Returns a conservative type bound for a value expression.
     * @param Term $value Expression or abstract fact
     * @return string Target type bound
     */
    public function type(Term $value): string
    {
        if ($value->kind === \Deriver\Deriver\Evaluator\TermKind::Constant) {
            return match (gettype($value->literal)) {
                'integer' => 'int', 'double' => 'float', 'boolean' => 'bool',
                'string' => 'string', 'NULL' => 'null',
            };
        }
        if (in_array($value->kind, [\Deriver\Deriver\Evaluator\TermKind::Array, \Deriver\Deriver\Evaluator\TermKind::Object, \Deriver\Deriver\Evaluator\TermKind::Closure, \Deriver\Deriver\Evaluator\TermKind::Enum], true)) {
            return $value->kind === \Deriver\Deriver\Evaluator\TermKind::Closure || $value->kind === \Deriver\Deriver\Evaluator\TermKind::Enum ? 'object' : $value->kind->value;
        }
        if ($value->kind === \Deriver\Deriver\Evaluator\TermKind::Concat) {
            return 'string';
        }
        if ($value->kind === \Deriver\Deriver\Evaluator\TermKind::Binary && ($value->attributes['type'] ?? '') === 'array') {
            return 'array';
        }
        if ($value->kind === \Deriver\Deriver\Evaluator\TermKind::Binary && in_array($value->literal, ['+', '-', '*', '/', '**'], true)) {
            return 'int|float';
        }
        $type = $value->attributes['type'] ?? 'mixed';
        return is_string($type) ? $type : 'mixed';
    }

    /**
     * Checks inclusion in the supported finite type lattice.
     * @param Term $upper Candidate upper bound
     * @param Term $lower Candidate contained value
     * @return bool Whether containment is established
     */
    public function contains(Term $upper, Term $lower): bool
    {
        if ($upper === $lower) {
            return true;
        }
        /** @var WeakMap<Term, WeakMap<Term, true>> $visited */
        $visited = new WeakMap();
        /** @var list<array{Term, Term}> $pending */
        $pending = [[$upper, $lower]];
        while ($pending !== []) {
            [$a, $b] = array_pop($pending);
            $memoized = $this->pairs[$a][$b] ?? null;
            if ($memoized === false) {
                return false;
            }
            if ($memoized === true || isset($visited[$a][$b])) {
                continue;
            }
            if (!$this->compatible($a, $b)) {
                /** @var WeakMap<Term, bool> $partners */
                $partners = new WeakMap();
                $partners = $this->pairs[$a] ?? $partners;
                $partners[$b] = false;
                $this->pairs[$a] = $partners;
                return false;
            }
            /** @var WeakMap<Term, true> $seen */
            $seen = new WeakMap();
            $seen = $visited[$a] ?? $seen;
            $seen[$b] = true;
            $visited[$a] = $seen;
            if ($a->kind === \Deriver\Deriver\Evaluator\TermKind::Array && $b->kind === \Deriver\Deriver\Evaluator\TermKind::Array) {
                foreach ($a->operands as $key => $value) {
                    $pending[] = [$value, $b->operands[$key]];
                }
            }
        }
        foreach ($visited as $upperTerm => $lowerTerms) {
            /** @var WeakMap<Term, bool> $partners */
            $partners = new WeakMap();
            $partners = $this->pairs[$upperTerm] ?? $partners;
            foreach ($lowerTerms as $lowerTerm => $_) {
                $partners[$lowerTerm] = true;
            }
            $this->pairs[$upperTerm] = $partners;
        }
        return true;
    }

    /**
     * Checks scalar inclusion and local array-shape obligations before visiting child pairs.
     * @param Term $upper Candidate containing fact
     * @param Term $lower Candidate contained fact
     * @return bool Whether this pair has compatible local shape or established scalar inclusion
     */
    public function compatible(Term $upper, Term $lower): bool
    {
        if ($upper->kind === \Deriver\Deriver\Evaluator\TermKind::Domain && $lower->kind === \Deriver\Deriver\Evaluator\TermKind::Domain && $upper->literal === $lower->literal && is_string($upper->literal) && isset($this->domains[$upper->literal])) {
            return (new DomainOperations())->contains($this->domains[$upper->literal], $upper, $lower);
        }
        if ($upper->kind === \Deriver\Deriver\Evaluator\TermKind::Abstract || $upper->kind === \Deriver\Deriver\Evaluator\TermKind::Opaque) {
            $type = $this->type($upper);
            return $type === 'mixed' || array_diff(explode('|', $this->type($lower)), explode('|', $type)) === [];
        }
        if ($upper->kind === \Deriver\Deriver\Evaluator\TermKind::Array && $lower->kind === \Deriver\Deriver\Evaluator\TermKind::Array) {
            return $this->shape($upper, $lower);
        }
        $prefix = (new StringPrefix())->bound($upper);
        if ($prefix !== null) {
            return $this->type($lower) === 'string' && (new StringPrefix())->contains($prefix, $lower);
        }
        return $this->identity->key($upper) === $this->identity->key($lower);
    }

    /**
     * Checks required key order and unknown remainders before comparing element values.
     * @param Term $upper Candidate containing array shape
     * @param Term $lower Candidate contained array shape
     * @return bool Whether their local shape obligations allow inclusion
     */
    public function shape(Term $upper, Term $lower): bool
    {
        if (array_keys($upper->operands) !== array_keys(array_intersect_key($lower->operands, $upper->operands))) {
            return false;
        }
        if (($lower->attributes['open'] ?? false) === true && ($upper->attributes['open'] ?? false) !== true) {
            return false;
        }
        return ($upper->attributes['open'] ?? false) === true || array_keys($upper->operands) === array_keys($lower->operands);
    }

    /**
     * Widens both inputs to a finite type fact that contains them, keeping the bytes two strings share at the start.
     * @param Term $previous Previous loop approximation
     * @param Term $next Newly propagated value
     * @return Term Inclusive widened value
     */
    public function widen(Term $previous, Term $next): Term
    {
        if ($this->contains($previous, $next)) {
            return $previous;
        }
        if ($previous->kind === \Deriver\Deriver\Evaluator\TermKind::Domain && $next->kind === \Deriver\Deriver\Evaluator\TermKind::Domain && $previous->literal === $next->literal && is_string($previous->literal) && isset($this->domains[$previous->literal])) {
            return (new DomainOperations())->apply($this->domains[$previous->literal], 'widen', $previous, $next);
        }
        $a = $this->type($previous);
        $b = $this->type($next);
        if ($a === 'string' && $b === 'string') {
            $prefixed = (new StringPrefix())->widen($previous, $next);
            if ($prefixed !== null) {
                return $prefixed;
            }
        }
        $types = array_values(array_unique([...explode('|', $a), ...explode('|', $b)]));
        sort($types);
        $type = in_array('mixed', $types, true) ? 'mixed' : implode('|', $types);
        if (in_array($type, ['int', 'float', 'float|int', 'int|float'], true)) {
            $type = 'int|float';
        }
        return new Term(\Deriver\Deriver\Evaluator\TermKind::Abstract, 'WIDENED', attributes: ['type' => $type]);
    }
}
