<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Scalar;

/**
 * Retains the bytes every string of a widened set starts with.
 *
 * A widened string is represented as `concat(constant prefix, abstract string)`. Its prefix only shrinks as more
 * strings are joined, so repeated widening terminates.
 * @visibility root
 */
final class StringPrefix
{
    /**
     * Reads the leading bytes a string term definitely starts with.
     * @param \Deriver\Deriver\Evaluator\Term $value String-typed term
     * @return array{string, bool} Known leading bytes, and whether they are the whole string
     */
    public function known(\Deriver\Deriver\Evaluator\Term $value): array
    {
        if ($value->kind === \Deriver\Deriver\Evaluator\TermKind::Constant) {
            return is_string($value->literal) ? [$value->literal, true] : ['', false];
        }
        if ($value->kind === \Deriver\Deriver\Evaluator\TermKind::Cast && $value->literal === 'string' && ($value->operands[0] ?? null)?->kind === \Deriver\Deriver\Evaluator\TermKind::Concat) {
            return $this->known($value->operands[0]);
        }
        if ($value->kind !== \Deriver\Deriver\Evaluator\TermKind::Concat || count($value->operands) !== 2) {
            return ['', false];
        }
        [$left, $right] = array_values($value->operands);
        [$prefix, $complete] = $this->known($left);
        if (!$complete) {
            return [$prefix, false];
        }
        [$rest, $complete] = $this->known($right);
        return [$prefix . $rest, $complete];
    }

    /**
     * Returns the prefix of a widened string term.
     * @param \Deriver\Deriver\Evaluator\Term $value Candidate upper bound
     * @return string|null Required leading bytes, or null when the term is not a widened string
     */
    public function bound(\Deriver\Deriver\Evaluator\Term $value): ?string
    {
        if ($value->kind !== \Deriver\Deriver\Evaluator\TermKind::Concat || count($value->operands) !== 2) {
            return null;
        }
        [$prefix, $rest] = array_values($value->operands);
        if ($prefix->kind !== \Deriver\Deriver\Evaluator\TermKind::Constant || !is_string($prefix->literal) || $prefix->literal === '' || $rest->kind !== \Deriver\Deriver\Evaluator\TermKind::Abstract || $rest->literal !== 'WIDENED' || ($rest->attributes['type'] ?? null) !== 'string') {
            return null;
        }
        return $prefix->literal;
    }

    /**
     * Checks that a string term starts with a widened string's prefix.
     * @param string $prefix Required leading bytes
     * @param \Deriver\Deriver\Evaluator\Term $lower String-typed term
     * @return bool Whether every value of the term has the prefix
     */
    public function contains(string $prefix, \Deriver\Deriver\Evaluator\Term $lower): bool
    {
        return str_starts_with($this->known($lower)[0], $prefix);
    }

    /**
     * Joins two string terms into the strings starting with their common known prefix.
     * @param \Deriver\Deriver\Evaluator\Term $previous Previous string bound
     * @param \Deriver\Deriver\Evaluator\Term $next Joined string
     * @return \Deriver\Deriver\Evaluator\Term|null Widened string, or null when no leading byte is shared
     */
    public function widen(\Deriver\Deriver\Evaluator\Term $previous, \Deriver\Deriver\Evaluator\Term $next): ?\Deriver\Deriver\Evaluator\Term
    {
        $a = $this->known($previous)[0];
        $b = $this->known($next)[0];
        $length = 0;
        $limit = min(strlen($a), strlen($b));
        while ($length < $limit && $a[$length] === $b[$length]) {
            $length++;
        }
        if ($length === 0) {
            return null;
        }
        return new \Deriver\Deriver\Evaluator\Term(\Deriver\Deriver\Evaluator\TermKind::Concat, operands: [\Deriver\Deriver\Evaluator\Term::constant(substr($a, 0, $length)), new \Deriver\Deriver\Evaluator\Term(\Deriver\Deriver\Evaluator\TermKind::Abstract, 'WIDENED', attributes: ['type' => 'string'])], attributes: ['type' => 'string']);
    }
}
