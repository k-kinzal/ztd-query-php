<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Candidate;

use Deriver\Value\Identity;
use Deriver\Value\Term;

/**
 * Keeps branch and call-site correlations inside shared expression choices.
 * @visibility root
 */
final class Choices
{
    /**
     * @param list<array{Term, array<string, bool>}> $alternatives
     */
    public function make(array $alternatives): Term
    {
        if (count($alternatives) === 1 && $alternatives[0][1] === [] && $alternatives[0][0]->kind !== 'choice') {
            return $alternatives[0][0];
        }
        $items = [];
        foreach ($alternatives as [$value, $guard]) {
            foreach ($this->alternatives($value) as [$child, $conditions]) {
                $merged = $this->merge($guard, $conditions);
                if ($merged !== null) {
                    ksort($merged);
                    $key = (new Identity())->key($child) . serialize($merged);
                    $attributes = [];
                    foreach ($merged as $condition => $truth) {
                        $attributes['guard:' . $condition] = $truth;
                    }
                    $items[$key] = new Term('alternative', operands: [$child], attributes: $attributes);
                }
            }
        }
        $items = array_values($items);
        if (count($items) === 1 && $items[0]->attributes === []) {
            return $items[0]->operands[0];
        }
        return new Term('choice', operands: $items);
    }

    /**

     * @return list<array{Term, array<string, bool>}>

     */
    public function alternatives(Term $value): array
    {
        if ($value->kind !== 'choice') {
            return [[$value, []]];
        }
        $alternatives = [];
        foreach ($value->operands as $item) {
            $guard = [];
            foreach ($item->attributes as $key => $condition) {
                if (str_starts_with($key, 'guard:') && is_bool($condition)) {
                    $guard[substr($key, 6)] = $condition;
                }
            }
            $alternatives[] = [$item->operands[0], $guard];
        }
        return $alternatives;
    }

    /**
     * @param array<string, bool> $left
     * @param array<string, bool> $right
     * @return array<string, bool>|null
     */
    public function merge(array $left, array $right): ?array
    {
        foreach ($right as $key => $value) {
            if (isset($left[$key]) && $left[$key] !== $value) {
                return null;
            }
        }
        return $left + $right;
    }

    /**
     * Distributes small choices and retains large products as an expression DAG.
     * @param list<Term> $operands
     * @param callable(list<Term>): Term $evaluate
     */
    public function apply(string $operation, array $operands, callable $evaluate, int $limit): Term
    {
        $rows = [[[], []]];
        foreach ($operands as $operand) {
            $next = [];
            foreach ($rows as [$values, $guard]) {
                foreach ($this->alternatives($operand) as [$value, $conditions]) {
                    $merged = $this->merge($guard, $conditions);
                    if ($merged !== null) {
                        $next[] = [[...$values, $value], $merged];
                    }
                    if (count($next) > $limit) {
                        return new Term('operation', $operation, $operands, ['reason' => 'ENUMERATION_LIMIT']);
                    }
                }
            }
            $rows = $next;
        }
        return $this->make(array_map(static fn (array $row): array => [$evaluate($row[0]), $row[1]], $rows));
    }
}
