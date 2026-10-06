<?php

declare(strict_types=1);

namespace Deriver\Model\Builtin;

use Deriver\Value\Operations;
use Deriver\Value\Term;

/**
 * Bounded construction of standard scalar and array values.
 * @visibility root
 */
final class ConstructionFunctions
{
    /**
     * @param string $name Standard intrinsic
     * @param list<Term> $values Signature-bound arguments
     * @return Term Constructed value, target error, or unresolved case
     */
    public function apply(string $name, array $values): Term
    {
        if ($name === 'intval') {
            return $this->integer($values);
        }
        $count = $values[1];
        if ($count->kind !== 'constant' || !is_int($count->literal)) {
            return Term::opaque('UNSUPPORTED_MODEL_CASE', dependencies: $values);
        }
        if ($count->literal < 0 || $name === 'array_fill' && $count->literal > 2147483647) {
            return new Term('throwable', 'ValueError');
        }
        if ($count->literal === 0) {
            return $name === 'array_fill' ? Term::array([]) : Term::constant('');
        }
        if ($name === 'array_fill') {
            $start = $values[0];
            if ($start->kind === 'constant' && is_int($start->literal) && $count->literal <= 4096 && $start->literal <= PHP_INT_MAX - ($count->literal - 1)) {
                return Term::array(array_fill($start->literal, $count->literal, $values[2]));
            }
        } elseif ($values[0]->kind === 'constant' && is_string($values[0]->literal)) {
            $string = $values[0]->literal;
            if ($string === '' || $count->literal <= intdiv(1048576, strlen($string))) {
                return Term::constant(str_repeat($string, $count->literal));
            }
        }
        return Term::opaque('UNSUPPORTED_MODEL_CASE', $name === 'array_fill' ? 'array' : 'string', $values);
    }

    /**
     * @param list<Term> $values Value and numeric base
     * @return Term Integer or explicit unsupported conversion
     */
    public function integer(array $values): Term
    {
        [$value, $base] = $values;
        if ($value->kind === 'constant' && !is_string($value->literal)) {
            return (new Operations())->cast('int', $value);
        }
        if ($base->kind === 'constant' && is_int($base->literal) && ($base->literal === 0 || $base->literal >= 2 && $base->literal <= 36)) {
            if ($value->kind === 'constant') {
                return Term::constant(intval($value->literal, $base->literal));
            }
            if ($base->literal === 10 && ($value->attributes['type'] ?? '') === 'string') {
                return (new Operations())->cast('int', $value);
            }
        }
        return Term::opaque('UNSUPPORTED_MODEL_CASE', 'int', $values);
    }
}
