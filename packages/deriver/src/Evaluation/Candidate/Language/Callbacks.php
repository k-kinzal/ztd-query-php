<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Candidate\Language;

use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Candidate\Derivation;
use Deriver\Evaluation\Candidate\Frame;
use Deriver\Evaluation\Candidate\Invocation\Callback;
use Deriver\Value\Operations;
use Deriver\Value\Term;

/**
 * Finite collection models request callbacks through the common call machinery.
 * @visibility root
 */
final class Callbacks
{
    /**
     * Expands supported callback intrinsics, retaining uncertain collection operations.
     * @param list<Term> $values Model operands
     */
    public function apply(Derivation $engine, Frame $frame, Instruction $instruction, array $values, int $depth): ?Term
    {
        $name = $instruction->name;
        if (!in_array($name, ['call_user_func', 'call_user_func_array', 'array_map', 'array_filter', 'array_reduce', 'callback-sort'], true)) {
            return null;
        }
        $invoke = (new Callback($engine, $frame, $instruction, $depth))->value(...);
        if (in_array($name, ['call_user_func', 'call_user_func_array'], true)) {
            return $values[1]->kind === 'array' && ($values[1]->attributes['open'] ?? false) === false ? $invoke($values[0], $values[1]->operands) : $this->residual($name, $values);
        }
        $array = $values[$name === 'array_map' ? 1 : 0];
        $callback = $values[$name === 'array_map' ? 0 : 1];
        if ($array->kind !== 'array' || ($array->attributes['open'] ?? false) === true) {
            return $this->residual($name, $values);
        }
        if ($name === 'callback-sort') {
            return $this->sort($array, $callback, $invoke);
        }
        if ($name === 'array_map' && ($values[2]->operands ?? []) !== []) {
            return $this->mapMany($values, $invoke);
        }
        return $this->collect($engine, $name, $array, $callback, $values, $invoke);
    }

    /**
     * Maps, filters or folds finite elements while retaining an interrupted prefix.
     * @param list<Term> $values Model operands
     * @param callable(Term, array<int|string, Term>): Term $invoke Common callback expansion
     */
    public function collect(Derivation $engine, string $name, Term $array, Term $callback, array $values, callable $invoke): Term
    {
        $entries = [];
        $carry = $values[2] ?? Term::constant(null);
        foreach ($array->operands as $key => $value) {
            if ($engine->context->work() !== null) {
                return $this->residual($name, [Term::array($entries), ...$values]);
            }
            if ($name === 'array_reduce') {
                $carry = $invoke($callback, [$carry, $value]);
                continue;
            }
            if ($name === 'array_map') {
                $entries[$key] = $callback->kind === 'constant' && $callback->literal === null ? $value : $invoke($callback, [$value]);
                continue;
            }
            $mode = $values[2]->literal ?? 0;
            $arguments = match ($mode) {
                1 => [$value, Term::constant($key)], 2 => [Term::constant($key)], default => [$value]
            };
            $test = $callback->kind === 'constant' && $callback->literal === null ? $value : $invoke($callback, $arguments);
            $truth = (new Operations())->truth($test);
            if ($truth === null) {
                return $this->residual($name, [Term::array($entries), $test, ...$values]);
            }
            if ($truth) {
                $entries[$key] = $value;
            }
        }
        return $name === 'array_reduce' ? $carry : Term::array($entries);
    }

    /**
     * Zips finite inputs as PHP array_map does, using null for missing positions.
     * @param list<Term> $values Callback, first array and variadic arrays
     * @param callable(Term, list<Term>): Term $invoke Common callback expansion
     */
    public function mapMany(array $values, callable $invoke): Term
    {
        $arrays = [$values[1], ...array_values($values[2]->operands)];
        foreach ($arrays as $array) {
            if ($array->kind !== 'array' || ($array->attributes['open'] ?? false) === true) {
                return $this->residual('array_map', $values);
            }
        }
        $rows = array_map(static fn (Term $array): array => array_values($array->operands), $arrays);
        $length = max(array_map('count', $rows));
        $entries = [];
        for ($index = 0; $index < $length; $index++) {
            $arguments = array_map(static fn (array $row): Term => $row[$index] ?? Term::constant(null), $rows);
            $entries[] = $values[0]->kind === 'constant' && $values[0]->literal === null ? Term::array($arguments) : $invoke($values[0], $arguments);
        }
        return Term::array($entries);
    }

    /**
     * Stably orders concrete comparisons without executing a host callback.
     * @param callable(Term, list<Term>): Term $invoke Common callback expansion
     */
    public function sort(Term $array, Term $callback, callable $invoke): Term
    {
        $entries = [];
        foreach ($array->operands as $value) {
            $position = count($entries);
            while ($position > 0) {
                $comparison = $invoke($callback, [$entries[$position - 1], $value]);
                if ($comparison->kind !== 'constant' || !is_int($comparison->literal)) {
                    return $this->residual('usort', [$array, $callback, $comparison]);
                }
                if ($comparison->literal <= 0) {
                    break;
                }
                $position--;
            }
            $entries = [...array_slice($entries, 0, $position), $value, ...array_slice($entries, $position)];
        }
        return Term::array($entries);
    }

    /**
     * Keeps callback and known operands when collection structure is unresolved.
     * @param list<Term> $values Known operands and any established prefix
     */
    public function residual(string $name, array $values): Term
    {
        return new Term('operation', $name, $values, ['reason' => 'UNRESOLVED_CALLBACK_INPUT']);
    }
}
