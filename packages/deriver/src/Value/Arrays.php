<?php

declare(strict_types=1);

namespace Deriver\Value;

/**
 * Ordered PHP array updates, append indices, and distinct unpack/merge semantics.
 * @visibility root
 */
final class Arrays
{
    /**
     * Chooses the next append index under PHP 8.3 semantics.
     * @param Term $array Array shape
     * @return int|null Append index or null when unknown/overflowing
     */
    public function next(Term $array): ?int
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
     * @param Term $array Existing array
     * @return Term Append key, an unknown index, or an occupied-maximum Error
     */
    public function appendKey(Term $array): Term
    {
        $next = $this->next($array);
        if ($next === null) {
            return Term::opaque('UNKNOWN_APPEND_INDEX', 'int', [$array]);
        }
        return $next === 9223372036854775807 && isset($array->operands[$next]) ? new Term('throwable', 'Error') : Term::constant($next);
    }

    /**
     * Updates a known key or preserves a conservative unknown-key remainder.
     * @param Term $array Array shape
     * @param Term|null $key Null denotes append syntax
     * @param Term $value Assigned value
     * @return Term Updated array or explicit throwable
     */
    public function set(Term $array, ?Term $key, Term $value): Term
    {
        if ($array->kind !== 'array') {
            return new Term('array-set', operands: [$array, $key ?? new Term('append'), $value], attributes: ['type' => 'array']);
        }
        if ($key === null) {
            $key = $this->appendKey($array);
        }
        $key = $key->kind === 'throwable' ? $key : (new Operations())->arrayKey($key);
        if ($key->kind === 'throwable') {
            return $key;
        }
        if ($key->kind !== 'constant' || (!is_string($key->literal) && !is_int($key->literal))) {
            $entries = [];
            foreach ($array->operands as $known => $previous) {
                $entries[$known] = Term::opaque('UNKNOWN_ARRAY_KEY', dependencies: [$previous, $value, $key]);
            }
            return new Term('array', operands: $entries, attributes: ['open' => true], secret: $array->isSecret() || $key->isSecret() || $value->isSecret());
        }
        $entries = $array->operands;
        $entries[$key->literal] = $value;
        $result = Term::array($entries, ($array->attributes['open'] ?? false) === true);
        $next = $this->next($result);
        $oldNext = $this->next($array);
        if ($oldNext !== null && $next !== null && ($array->operands !== [] || array_key_exists('next', $array->attributes))) {
            $next = max($oldNext, $next);
        }
        return new Term('array', operands: $entries, attributes: ['open' => ($array->attributes['open'] ?? false) === true, 'next' => $next], secret: $array->isSecret() || $key->isSecret());
    }

    /**
     * Appends numeric entries and overwrites named entries for unpack/array_merge.
     * @param Term $left Destination
     * @param Term $right Source
     * @return Term Merged array with conservative remainder
     */
    public function merge(Term $left, Term $right): Term
    {
        if ($left->kind !== 'array' || $right->kind !== 'array' || ($left->attributes['open'] ?? false) === true || ($right->attributes['open'] ?? false) === true) {
            return new Term('array-merge', operands: [$left, $right], attributes: ['type' => 'array']);
        }
        foreach ($right->operands as $key => $value) {
            $left = $this->set($left, is_int($key) ? null : Term::constant($key), $value);
            if ($left->kind === 'throwable') {
                return $left;
            }
        }
        return $right->isSecret() ? new Term($left->kind, $left->literal, $left->operands, $left->attributes, true) : $left;
    }

    /**
     * Finds the closed integer-keyed destination at the start of a symbolic merge, whose entries no source can replace or reorder.
     * @param Term $array Possibly symbolic merge
     * @return Term|null Leading entries in order, or null when the merge has no such destination
     */
    public function head(Term $array): ?Term
    {
        while ($array->kind === 'array-set' && ($array->operands[1]->kind ?? '') === 'append') {
            $array = $array->operands[0];
        }
        if ($array->kind !== 'array-merge') {
            return null;
        }
        while ($array->kind === 'array-merge' && isset($array->operands[0])) {
            $array = $array->operands[0];
        }
        if ($array->kind !== 'array' || ($array->attributes['open'] ?? false) === true || $array->operands === [] || array_filter(array_keys($array->operands), is_string(...)) !== []) {
            return null;
        }
        return $array;
    }

    /**
     * Separates a single symbolic merge into its known head and the source appended after it.
     * @param Term $array Possibly symbolic merge
     * @return array{Term, Term}|null Head and appended source, or null when either part is not separable
     */
    public function split(Term $array): ?array
    {
        $head = $this->head($array);
        if ($head === null || $array->operands[0] !== $head || !isset($array->operands[1])) {
            return null;
        }
        return [$head, $array->operands[1]];
    }

    /**
     * Separates trailing append entries whose order is known after an unknown array.
     * @param Term $array Constructed array
     * @return array{Term, Term}|null Unknown prefix and closed suffix
     */
    public function tail(Term $array): ?array
    {
        $items = [];
        while ($array->kind === 'array-set' && ($array->operands[1]->kind ?? '') === 'append') {
            $items[] = $array->operands[2];
            $array = $array->operands[0];
        }
        return $items === [] || !$this->appendable($array) ? null : [$array, Term::array(array_reverse($items))];
    }

    /**
     * Checks that appending arrays cannot reach the occupied maximum append index; a PHP array holds fewer than 2^32 elements.
     * @param Term $array Unpack destination
     * @return bool Whether every later append index is provably available
     */
    public function appendable(Term $array): bool
    {
        $appended = 0;
        while ($array->kind === 'array-merge' && isset($array->operands[0])) {
            $appended++;
            $array = $array->operands[0];
        }
        $next = $array->kind === 'array' ? $this->next($array) : null;
        return $next !== null && $next <= PHP_INT_MAX - ($appended + 1) * 4294967296;
    }
}
