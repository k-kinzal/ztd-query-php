<?php

declare(strict_types=1);

namespace Deriver\Model\Builtin;

use Deriver\Value\Arrays;
use Deriver\Value\Comparison;
use Deriver\Value\Operations;
use Deriver\Value\Term;

/**
 * Preserves array keys, order, and unknown remainders in standard operations.
 * @visibility root
 */
final class ArrayFunctions
{
    /**
     * @param int|null $floatPrecision Captured target precision for float-to-string conversion; null when unknown
     */
    public function __construct(public readonly ?int $floatPrecision = null)
    {
    }

    /**
     * Evaluates a supported standard array function.
     * @param string $name Function name
     * @param list<Term> $values Bound arguments
     * @return Term Abstract result
     */
    public function apply(string $name, array $values): Term
    {
        $array = $values[0] ?? Term::constant(null);
        if ($name === 'array_key_exists' || $name === 'in_array') {
            return $this->membership($name, $values);
        }
        if ($name === 'count') {
            return $this->countArguments($values);
        }
        if ($name === 'array_key_first' || $name === 'array_key_last') {
            return $this->boundaryKey($name, $array);
        }
        if ($name === 'array_slice') {
            return $this->slice($values);
        }
        if ($array->kind !== 'array') {
            return new Term('intrinsic', $name, $values, ['type' => 'array']);
        }
        if ($name === 'array_merge') {
            $result = Term::array([]);
            foreach ($array->operands as $item) {
                $result = (new Arrays())->merge($result, $item);
            }
            return $result;
        }
        if (($array->attributes['open'] ?? false) === true) {
            return new Term('intrinsic', $name, $values, ['type' => 'array']);
        }
        if ($name === 'array_keys') {
            return $this->keys($array, $values);
        }
        return Term::array(array_values($array->operands));
    }

    /**
     * Selects the first or last key of a closed shape; an open shape keeps the call symbolic.
     * @param string $name array_key_first or array_key_last
     * @param Term $array Bound array
     * @return Term Key, null for an empty array, or a symbolic key
     */
    public function boundaryKey(string $name, Term $array): Term
    {
        if ($array->kind !== 'array' || ($array->attributes['open'] ?? false) === true) {
            return new Term('intrinsic', $name, [$array], ['type' => 'int|string|null']);
        }
        return Term::constant($name === 'array_key_first' ? array_key_first($array->operands) : array_key_last($array->operands));
    }

    /**
     * Slices a closed shape with constant bounds, renumbering integer keys unless they are preserved.
     * @param list<Term> $values Bound array, offset, length, and preserve_keys
     * @return Term Selected entries or a symbolic slice
     */
    public function slice(array $values): Term
    {
        [$array, $offset, $length, $preserve] = $values + [Term::array([]), Term::constant(0), Term::constant(null), Term::constant(false)];
        if ($array->kind !== 'array' || ($array->attributes['open'] ?? false) === true || $offset->kind !== 'constant' || !is_int($offset->literal) || $length->kind !== 'constant' || (!is_int($length->literal) && $length->literal !== null) || $preserve->kind !== 'constant' || !is_bool($preserve->literal)) {
            return new Term('intrinsic', 'array_slice', $values, ['type' => 'array']);
        }
        return new Term('array', operands: array_slice($array->operands, $offset->literal, $length->literal, $preserve->literal), attributes: ['open' => false], secret: $array->secret);
    }

    /**
     * Validates count modes before deciding whether an array shape is available.
     * @param list<Term> $values Bound value and mode
     * @return Term Count, ValueError, or an explicit protocol boundary
     */
    public function countArguments(array $values): Term
    {
        $array = $values[0] ?? Term::constant(null);
        $mode = $values[1] ?? Term::constant(0);
        if ($mode->kind !== 'constant') {
            return Term::opaque('UNSUPPORTED_MODEL_CASE', 'int', $values);
        }
        if (!in_array($mode->literal, [0, 1], true)) {
            return new Term('throwable', 'ValueError');
        }
        if ($array->kind === 'array' && ($array->attributes['open'] ?? false) === false) {
            return $this->count($array, $mode->literal === 1);
        }
        if ($mode->literal === 0 && (new TypePredicates())->apply('is_array', $array)->literal === true) {
            return new Term('intrinsic', 'count', $values, ['type' => 'int']);
        }
        return Term::opaque('UNSUPPORTED_MODEL_CASE', 'int', $values);
    }

    /**
     * Counts finite shapes, preserving the recursive-mode distinction.
     * @param Term $array Closed array shape
     * @param bool $recursive Whether to count nested arrays
     * @return Term Count or residual when a nested shape is unknown
     */
    public function count(Term $array, bool $recursive): Term
    {
        $count = count($array->operands);
        if ($recursive) {
            foreach ($array->operands as $element) {
                if ($element->kind === 'array' && ($element->attributes['open'] ?? false) === false) {
                    $nested = $this->count($element, true);
                    if (!is_int($nested->literal)) {
                        return $nested;
                    }
                    $count += $nested->literal;
                } elseif ($element->kind !== 'constant' && $element->kind !== 'object') {
                    return Term::opaque('UNSUPPORTED_MODEL_CASE', 'int', [$array]);
                }
            }
        }
        return Term::constant($count, $array->isSecret());
    }

    /**
     * Evaluates membership while retaining uncertainty about unknown array parts, including sources merged after a known head.
     * @param string $name Membership function
     * @param list<Term> $values Bound arguments
     * @return Term Boolean predicate
     */
    public function membership(string $name, array $values): Term
    {
        $needle = $values[0] ?? Term::constant(null);
        $array = $values[1] ?? Term::constant(null);
        $head = (new Arrays())->head($array);
        if ($head !== null) {
            $found = $this->membership($name, [$needle, new Term('array', operands: $head->operands, attributes: ['open' => true], secret: $array->isSecret()), ...array_slice($values, 2)]);
            return $found->kind === 'constant' && $found->literal === true ? $found : new Term('intrinsic', $name, $values, ['type' => 'bool']);
        }
        if ($array->kind !== 'array') {
            return new Term('intrinsic', $name, $values, ['type' => 'bool']);
        }
        if ($name === 'array_key_exists') {
            return $this->keyExists($needle, $array);
        }
        if (($values[2]->kind ?? 'constant') !== 'constant') {
            return new Term('intrinsic', $name, $values, ['type' => 'bool']);
        }
        $unknown = false;
        foreach ($array->operands as $element) {
            $comparison = (new Comparison($this->floatPrecision))->apply(($values[2]->literal ?? false) === true ? '===' : '==', $needle, $element);
            if ($comparison->kind === 'constant' && $comparison->literal === true) {
                return Term::constant(true);
            }
            $unknown = $unknown || $comparison->kind !== 'constant';
        }
        if (!$unknown && ($array->attributes['open'] ?? false) === false) {
            return Term::constant(false);
        }

        return new Term('intrinsic', $name, $values, ['type' => 'bool']);
    }

    /**
     * Selects array keys with the target strict or loose comparison semantics.
     * @param Term $array Closed array shape
     * @param list<Term> $values Bound filter arguments
     * @return Term Ordered selected keys
     */
    public function keys(Term $array, array $values): Term
    {
        $filter = $values[1] ?? new Term('omitted');
        if ($filter->kind === 'omitted') {
            return Term::array(array_map(static fn (int|string $key): Term => Term::constant($key), array_keys($array->operands)));
        }
        $strict = $values[2] ?? Term::constant(false);
        if ($strict->kind !== 'constant') {
            return Term::opaque('UNSUPPORTED_MODEL_CASE', 'array', $values);
        }
        $keys = [];
        foreach ($array->operands as $key => $element) {
            $equal = (new Comparison($this->floatPrecision))->apply($strict->literal === true ? '===' : '==', $filter, $element);
            if ($equal->kind !== 'constant') {
                return Term::opaque('UNSUPPORTED_MODEL_CASE', 'array', $values);
            }
            if ($equal->literal === true) {
                $keys[] = Term::constant($key);
            }
        }
        return Term::array($keys);
    }

    /**
     * Checks key presence without treating an open remainder as absent or an entry that unknown code may have removed as present.
     * @param Term $needle Key value
     * @param Term $array Known array shape
     * @return Term Presence predicate
     */
    public function keyExists(Term $needle, Term $array): Term
    {
        $key = (new Operations())->arrayKey($needle);
        if ($key->kind === 'throwable') {
            return $key;
        }
        if ($key->kind === 'constant' && (is_int($key->literal) || is_string($key->literal))) {
            if (array_key_exists($key->literal, $array->operands) && ($array->operands[$key->literal]->attributes['maybeUninitialized'] ?? false) !== true) {
                return Term::constant(true);
            }
            if (($array->attributes['open'] ?? false) === false) {
                return Term::constant(false);
            }
        }
        return new Term('intrinsic', 'array_key_exists', [$needle, $array], ['type' => 'bool']);
    }
}
