<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Scalar;

/**
 * Ordered PHP array updates, append indices, and distinct unpack/merge semantics.
 * @visibility root
 */
final class Arrays
{
    /**
     * Chooses the next append index under PHP 8.3 semantics.
     * @param \Deriver\Deriver\Evaluator\Term $array Array shape
     * @return int|null Append index or null when unknown/overflowing
     */
    public function next(\Deriver\Deriver\Evaluator\Term $array): ?int
    {
        if (($array->attributes['open'] ?? false) === true) {
            return null;
        }
        if (isset($array->attributes['next']) && is_int($array->attributes['next'])) {
            return $array->attributes['next'];
        }
        $max = null;
        foreach (array_keys($array->operands) as $key) {
            if (is_int($key)) {
                $max = $max === null ? $key : max($max, $key);
            }
        }
        return $max === 9223372036854775807 ? $max : ($max === null ? 0 : $max + 1);
    }

    /**
     * Validates the capped PHP append counter independently of an explicit integer assignment.
     * @param \Deriver\Deriver\Evaluator\Term $array Existing array
     * @return \Deriver\Deriver\Evaluator\Term Append key, an unknown index, or an occupied-maximum Error
     */
    public function appendKey(\Deriver\Deriver\Evaluator\Term $array): \Deriver\Deriver\Evaluator\Term
    {
        $next = $this->next($array);
        if ($next === null) {
            return \Deriver\Deriver\Evaluator\Term::opaque('UNKNOWN_APPEND_INDEX', 'int', [$array]);
        }
        return $next === 9223372036854775807 && isset($array->operands[$next]) ? new \Deriver\Deriver\Evaluator\Term(\Deriver\Deriver\Evaluator\TermKind::Throwable, 'Error') : \Deriver\Deriver\Evaluator\Term::constant($next);
    }

    /**
     * Updates a known key or preserves a conservative unknown-key remainder.
     * @param \Deriver\Deriver\Evaluator\Term $array Array shape
     * @param \Deriver\Deriver\Evaluator\Term|null $key Null denotes append syntax
     * @param \Deriver\Deriver\Evaluator\Term $value Assigned value
     * @return \Deriver\Deriver\Evaluator\Term Updated array or explicit throwable
     */
    public function set(\Deriver\Deriver\Evaluator\Term $array, ?\Deriver\Deriver\Evaluator\Term $key, \Deriver\Deriver\Evaluator\Term $value): \Deriver\Deriver\Evaluator\Term
    {
        if ($array->kind !== \Deriver\Deriver\Evaluator\TermKind::Array) {
            return new \Deriver\Deriver\Evaluator\Term(\Deriver\Deriver\Evaluator\TermKind::ArraySet, operands: [$array, $key ?? new \Deriver\Deriver\Evaluator\Term(\Deriver\Deriver\Evaluator\TermKind::Append), $value], attributes: ['type' => 'array']);
        }
        if ($key === null) {
            $key = $this->appendKey($array);
        }
        $key = $key->kind === \Deriver\Deriver\Evaluator\TermKind::Throwable ? $key : (new Operations())->arrayKey($key);
        if ($key->kind === \Deriver\Deriver\Evaluator\TermKind::Throwable) {
            return $key;
        }
        if ($key->kind !== \Deriver\Deriver\Evaluator\TermKind::Constant || (!is_string($key->literal) && !is_int($key->literal))) {
            $entries = [];
            foreach ($array->operands as $known => $previous) {
                $entries[$known] = \Deriver\Deriver\Evaluator\Term::opaque('UNKNOWN_ARRAY_KEY', dependencies: [$previous, $value, $key]);
            }
            return new \Deriver\Deriver\Evaluator\Term(\Deriver\Deriver\Evaluator\TermKind::Array, operands: $entries, attributes: ['open' => true]);
        }
        $entries = $array->operands;
        $entries[$key->literal] = $value;
        $result = \Deriver\Deriver\Evaluator\Term::array($entries, ($array->attributes['open'] ?? false) === true);
        $next = $this->next($result);
        $oldNext = $this->next($array);
        if ($oldNext !== null && $next !== null && ($array->operands !== [] || array_key_exists('next', $array->attributes))) {
            $next = max($oldNext, $next);
        }
        return new \Deriver\Deriver\Evaluator\Term(\Deriver\Deriver\Evaluator\TermKind::Array, operands: $entries, attributes: ['open' => ($array->attributes['open'] ?? false) === true, 'next' => $next]);
    }

    /**
     * Appends numeric entries and overwrites named entries for unpack/array_merge.
     * @param \Deriver\Deriver\Evaluator\Term $left Destination
     * @param \Deriver\Deriver\Evaluator\Term $right Source
     * @return \Deriver\Deriver\Evaluator\Term Merged array with conservative remainder
     */
    public function merge(\Deriver\Deriver\Evaluator\Term $left, \Deriver\Deriver\Evaluator\Term $right): \Deriver\Deriver\Evaluator\Term
    {
        if ($left->kind !== \Deriver\Deriver\Evaluator\TermKind::Array || $right->kind !== \Deriver\Deriver\Evaluator\TermKind::Array || ($left->attributes['open'] ?? false) === true || ($right->attributes['open'] ?? false) === true) {
            return new \Deriver\Deriver\Evaluator\Term(\Deriver\Deriver\Evaluator\TermKind::ArrayMerge, operands: [$left, $right], attributes: ['type' => 'array']);
        }
        foreach ($right->operands as $key => $value) {
            $left = $this->set($left, is_int($key) ? null : \Deriver\Deriver\Evaluator\Term::constant($key), $value);
            if ($left->kind === \Deriver\Deriver\Evaluator\TermKind::Throwable) {
                return $left;
            }
        }
        return $left;
    }

    /**
     * Finds the closed integer-keyed destination at the start of a symbolic merge, whose entries no source can replace or reorder.
     * @param \Deriver\Deriver\Evaluator\Term $array Possibly symbolic merge
     * @return \Deriver\Deriver\Evaluator\Term|null Leading entries in order, or null when the merge has no such destination
     */
    public function head(\Deriver\Deriver\Evaluator\Term $array): ?\Deriver\Deriver\Evaluator\Term
    {
        while ($array->kind === \Deriver\Deriver\Evaluator\TermKind::ArraySet && ($array->operands[1]->kind ?? null) === \Deriver\Deriver\Evaluator\TermKind::Append) {
            $array = $array->operands[0];
        }
        if ($array->kind !== \Deriver\Deriver\Evaluator\TermKind::ArrayMerge) {
            return null;
        }
        while ($array->kind === \Deriver\Deriver\Evaluator\TermKind::ArrayMerge && isset($array->operands[0])) {
            $array = $array->operands[0];
        }
        if ($array->kind !== \Deriver\Deriver\Evaluator\TermKind::Array || ($array->attributes['open'] ?? false) === true || $array->operands === [] || array_filter(array_keys($array->operands), is_string(...)) !== []) {
            return null;
        }
        return $array;
    }

    /**
     * Separates a single symbolic merge into its known head and the source appended after it.
     * @param \Deriver\Deriver\Evaluator\Term $array Possibly symbolic merge
     * @return array{\Deriver\Deriver\Evaluator\Term, \Deriver\Deriver\Evaluator\Term}|null Head and appended source, or null when either part is not separable
     */
    public function split(\Deriver\Deriver\Evaluator\Term $array): ?array
    {
        $head = $this->head($array);
        if ($head === null || $array->operands[0] !== $head || !isset($array->operands[1])) {
            return null;
        }
        return [$head, $array->operands[1]];
    }

    /**
     * Separates trailing append entries whose order is known after an unknown array.
     * @param \Deriver\Deriver\Evaluator\Term $array Constructed array
     * @return array{\Deriver\Deriver\Evaluator\Term, \Deriver\Deriver\Evaluator\Term}|null Unknown prefix and closed suffix
     */
    public function tail(\Deriver\Deriver\Evaluator\Term $array): ?array
    {
        $items = [];
        while ($array->kind === \Deriver\Deriver\Evaluator\TermKind::ArraySet && ($array->operands[1]->kind ?? null) === \Deriver\Deriver\Evaluator\TermKind::Append) {
            $items[] = $array->operands[2];
            $array = $array->operands[0];
        }
        return $items === [] || !$this->appendable($array) ? null : [$array, \Deriver\Deriver\Evaluator\Term::array(array_reverse($items))];
    }

    /**
     * Checks that appending arrays cannot reach the occupied maximum append index; a PHP array holds fewer than 2^32 elements.
     * @param \Deriver\Deriver\Evaluator\Term $array Unpack destination
     * @return bool Whether every later append index is provably available
     */
    public function appendable(\Deriver\Deriver\Evaluator\Term $array): bool
    {
        $appended = 0;
        while ($array->kind === \Deriver\Deriver\Evaluator\TermKind::ArrayMerge && isset($array->operands[0])) {
            $appended++;
            $array = $array->operands[0];
        }
        $next = $array->kind === \Deriver\Deriver\Evaluator\TermKind::Array ? $this->next($array) : null;
        return $next !== null && $next <= PHP_INT_MAX - ($appended + 1) * 4294967296;
    }
}
