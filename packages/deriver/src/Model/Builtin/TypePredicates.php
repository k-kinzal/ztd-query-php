<?php

declare(strict_types=1);

namespace Deriver\Model\Builtin;

use Deriver\Value\Term;

/**
 * Evaluates type predicates and retains unknown predicates symbolically.
 * @visibility root
 */
final class TypePredicates
{
    /**
     * Evaluates one supported predicate.
     * @param string $name Predicate name
     * @param Term $value Abstract operand
     * @return Term Boolean expression
     */
    public function apply(string $name, Term $value): Term
    {
        if ($name === 'is_callable') {
            return in_array($value->kind, ['closure', 'callable', 'callable-method'], true) ? Term::constant(true) : new Term('intrinsic', $name, [$value], ['type' => 'bool']);
        }
        if ($value->kind === 'constant') {
            $v = $value->literal;
            $result = match ($name) {
                'is_string' => is_string($v), 'is_int', 'is_integer' => is_int($v),
                'is_float', 'is_double' => is_float($v), 'is_bool' => is_bool($v),
                'is_null' => $v === null, 'is_numeric' => is_numeric($v),
                'is_scalar' => is_scalar($v), 'is_array', 'is_object', 'is_callable' => false,
                default => false,
            };
            return Term::constant($result);
        }
        if (in_array($value->kind, ['array', 'object', 'closure', 'enum'], true)) {
            return Term::constant(match ($name) {
                'is_array' => $value->kind === 'array',
                'is_object' => in_array($value->kind, ['object', 'closure', 'enum'], true),
                'is_callable' => $value->kind === 'closure',
                default => false,
            });
        }
        $known = $this->bound($name, $value);
        return $known === null ? new Term('intrinsic', $name, [$value], ['type' => 'bool']) : Term::constant($known, $value->isSecret());
    }

    /**
     * Evaluates predicates established by a finite primitive type bound.
     * @param string $name Predicate name
     * @param Term $value Symbolic input with a declared type
     * @return bool|null Proven truth, or an unresolved predicate
     */
    public function bound(string $name, Term $value): ?bool
    {
        $type = $value->attributes['type'] ?? 'mixed';
        if (!is_string($type)) {
            return null;
        }
        $types = explode('|', $type);
        if (array_diff($types, ['int','float','string','bool','true','false','null','array','object']) !== [] || $name === 'is_numeric' && in_array('string', $types, true)) {
            return null;
        }
        $positive = match ($name) {
            'is_int', 'is_integer' => ['int'], 'is_float', 'is_double' => ['float'],
            'is_string' => ['string'], 'is_bool' => ['bool','true','false'],
            'is_null' => ['null'], 'is_array' => ['array'], 'is_object' => ['object'],
            'is_scalar' => ['int','float','string','bool','true','false'], 'is_numeric' => ['int','float'],
            default => null,
        };
        if ($positive === null) {
            return null;
        }
        if (array_diff($types, $positive) === []) {
            return true;
        }
        return array_intersect($types, $positive) === [] ? false : null;
    }
}
