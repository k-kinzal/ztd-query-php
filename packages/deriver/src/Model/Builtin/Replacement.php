<?php

declare(strict_types=1);

namespace Deriver\Model\Builtin;

use Deriver\Value\Term;

/**
 * Computes the replacement result and reference count as one correlated value.
 * @visibility root
 */
final class Replacement
{
    /**
     * Evaluates byte replacement for concrete string or string-array operands.
     * @param list<Term> $values Search, replacement, subject, and optional count
     * @return Term Pair of result and count, or an explicit residual
     */
    public function apply(array $values): Term
    {
        $search = $this->strings($values[0] ?? Term::constant(null));
        $replace = $this->strings($values[1] ?? Term::constant(null));
        $subject = $this->strings($values[2] ?? Term::constant(null));
        if ($search === null || $replace === null || $subject === null) {
            return Term::opaque('UNSUPPORTED_MODEL_CASE', dependencies: $values);
        }
        if (is_string($search) && is_array($replace)) {
            return new Term('throwable', 'TypeError');
        }
        $count = 0;
        $result = str_replace($search, $replace, $subject, $count);
        return Term::array(['result' => Term::fromNative($result), 'count' => Term::constant($count)]);
    }

    /**
     * Validates the supported native byte-string shape before calling a pure primitive.
     * @param Term $value Candidate shape
     * @return string|array<int|string, string>|null Supported data or null
     */
    public function strings(Term $value): string|array|null
    {
        if ($value->kind === 'constant' && is_string($value->literal)) {
            return $value->literal;
        }
        if ($value->kind !== 'array' || ($value->attributes['open'] ?? false) === true) {
            return null;
        }
        $result = [];
        foreach ($value->operands as $key => $element) {
            if ($element->kind !== 'constant' || !is_string($element->literal)) {
                return null;
            }
            $result[$key] = $element->literal;
        }
        return $result;
    }
}
