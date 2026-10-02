<?php

declare(strict_types=1);

namespace Deriver\Model\Builtin;

use Deriver\Value\Arrays;
use Deriver\Value\Term;

/**
 * Computes the updated array and return value of by-reference array mutations as one correlated value.
 * @visibility root
 */
final class ArrayMutation
{
    /**
     * Evaluates array_shift, array_pop, array_push, or array_unshift without writing the reference.
     * @param string $name Mutating function
     * @param list<Term> $values Bound array and, for insertions, the variadic value list
     * @return Term Mutation record with `array` and `result` (array_push adds `appended` and `partial`), a throwable, or a residual
     */
    public function apply(string $name, array $values): Term
    {
        $array = $values[0] ?? Term::constant(null);
        if ($name === 'array_shift' || $name === 'array_pop') {
            return $this->remove($array, $name === 'array_shift');
        }
        $items = $values[1] ?? Term::array([]);
        if ($items->kind !== 'array' || ($items->attributes['open'] ?? false) === true) {
            return Term::opaque('UNSUPPORTED_MODEL_CASE', 'array', $values);
        }
        if (array_filter(array_keys($items->operands), is_string(...)) !== []) {
            return new Term('throwable', 'ArgumentCountError');
        }
        $items = array_values($items->operands);
        return $name === 'array_push' ? $this->push($array, $items) : $this->prepend($array, $items);
    }

    /**
     * Removes the first or last entry of an array already known to be possibly non-empty.
     * @param Term $array Current array
     * @param bool $first Whether to remove the first entry and renumber integer keys
     * @return Term Mutation record
     */
    public function remove(Term $array, bool $first): Term
    {
        if (!$this->closed($array)) {
            $name = $first ? 'array_shift' : 'array_pop';
            return $this->record(new Term('intrinsic', $name . ':array', [$array], ['type' => 'array']), new Term('intrinsic', $name, [$array], ['type' => 'mixed']));
        }
        $entries = $array->operands;
        if ($entries === []) {
            return $this->record($array, Term::constant(null));
        }
        if ($first) {
            $value = array_shift($entries);
            return $this->record(new Term('array', operands: $entries, attributes: ['open' => false], secret: $array->secret), $value);
        }
        $key = array_key_last($entries);
        $value = $entries[$key];
        unset($entries[$key]);
        $next = (new Arrays())->next($array);
        if (is_int($key) && $next !== null && $key === $next - 1) {
            $next = $key;
        }
        return $this->record(new Term('array', operands: $entries, attributes: ['open' => false, 'next' => $next], secret: $array->secret), $value);
    }

    /**
     * Appends values with the capped next-index counter; PHP keeps the values inserted before an occupied maximum index.
     * @param Term $array Current array
     * @param list<Term> $items Appended values
     * @return Term Mutation record whose `appended` predicate selects `array` and `result`, or the `partial` array and an Error
     */
    public function push(Term $array, array $items): Term
    {
        $arrays = new Arrays();
        if (!$this->closed($array)) {
            $merged = $items === [] ? $array : $arrays->merge($array, Term::array($items));
            $appended = $items === [] || $arrays->appendable($array) ? Term::constant(true) : new Term('intrinsic', 'array_push:appendable', [$array, Term::constant(count($items))], ['type' => 'bool']);
            $partial = count($items) <= 1 ? $array : new Term('intrinsic', 'array_push:partial', [$array, Term::array($items)], ['type' => 'array']);
            return $this->record($merged, $this->count($merged), $appended, $partial);
        }
        $result = $array;
        foreach ($items as $item) {
            $next = $arrays->set($result, null, $item);
            if ($next->kind === 'throwable') {
                return $this->record($result, Term::constant(null), Term::constant(false), $result);
            }
            $result = $next;
        }
        return $this->record($result, Term::constant(count($result->operands)), Term::constant(true), $result);
    }

    /**
     * Prepends values and renumbers integer keys; string keys keep their order.
     * @param Term $array Current array
     * @param list<Term> $items Prepended values
     * @return Term Mutation record
     */
    public function prepend(Term $array, array $items): Term
    {
        if (!$this->closed($array)) {
            $merged = (new Arrays())->merge(Term::array($items), $array);
            return $this->record($merged, $this->count($merged));
        }
        $entries = $array->operands;
        array_unshift($entries, ...$items);
        return $this->record(new Term('array', operands: $entries, attributes: ['open' => false], secret: $array->secret), Term::constant(count($entries)));
    }

    /**
     * Reports whether every entry and the next index of an array are known.
     * @param Term $array Candidate array
     * @return bool Whether the array is a closed shape
     */
    public function closed(Term $array): bool
    {
        return $array->kind === 'array' && ($array->attributes['open'] ?? false) === false;
    }

    /**
     * Denotes the element count of a symbolic array exactly as a later count() call does.
     * @param Term $array Updated array
     * @return Term Symbolic count
     */
    public function count(Term $array): Term
    {
        return new Term('intrinsic', 'count', [$array, Term::constant(0)], ['type' => 'int']);
    }

    /**
     * Builds the record read by the declarative model plan.
     * @param Term $array Updated array
     * @param Term $result Return value
     * @param Term|null $appended Whether every value was appended, for array_push
     * @param Term|null $partial Array left by a failed append
     * @return Term Mutation record
     */
    public function record(Term $array, Term $result, ?Term $appended = null, ?Term $partial = null): Term
    {
        $record = ['array' => $array, 'result' => $result];
        if ($appended !== null && $partial !== null) {
            $record += ['appended' => $appended, 'partial' => $partial];
        }
        return Term::array($record);
    }
}
