<?php

declare(strict_types=1);

namespace Deriver\Value;

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
     * @param Term $value String-typed term
     * @return array{string, bool} Known leading bytes, and whether they are the whole string
     */
    public function known(Term $value): array
    {
        if ($value->kind === 'constant') {
            return is_string($value->literal) ? [$value->literal, true] : ['', false];
        }
        if ($value->kind === 'cast' && $value->literal === 'string' && ($value->operands[0] ?? null)?->kind === 'concat') {
            return $this->known($value->operands[0]);
        }
        if ($value->kind !== 'concat' || count($value->operands) !== 2) {
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
     * @param Term $value Candidate upper bound
     * @return string|null Required leading bytes, or null when the term is not a widened string
     */
    public function bound(Term $value): ?string
    {
        if ($value->kind !== 'concat' || count($value->operands) !== 2) {
            return null;
        }
        [$prefix, $rest] = array_values($value->operands);
        if ($prefix->kind !== 'constant' || !is_string($prefix->literal) || $prefix->literal === '' || $rest->kind !== 'abstract' || $rest->literal !== 'WIDENED' || ($rest->attributes['type'] ?? null) !== 'string') {
            return null;
        }
        return $prefix->literal;
    }

    /**
     * Checks that a string term starts with a widened string's prefix.
     * @param string $prefix Required leading bytes
     * @param Term $lower String-typed term
     * @return bool Whether every value of the term has the prefix
     */
    public function contains(string $prefix, Term $lower): bool
    {
        return str_starts_with($this->known($lower)[0], $prefix);
    }

    /**
     * Joins two string terms into the strings starting with their common known prefix.
     * @param Term $previous Previous string bound
     * @param Term $next Joined string
     * @return Term|null Widened string, or null when no leading byte is shared
     */
    public function widen(Term $previous, Term $next): ?Term
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
        $secret = $previous->isSecret() || $next->isSecret();
        return new Term('concat', operands: [Term::constant(substr($a, 0, $length), $secret), new Term('abstract', 'WIDENED', attributes: ['type' => 'string'], secret: $secret)], attributes: ['type' => 'string']);
    }
}
